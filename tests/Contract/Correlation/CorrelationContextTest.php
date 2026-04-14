<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Correlation;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class CorrelationContextTest extends TestCase
{
    private const JOB_UUID    = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
    private const CHANNEL_UUID = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78';

    private FrameParser              $parser;
    private InboundMessageClassifier $classifier;
    private ReplyFactory             $replyFactory;
    private EventParser              $eventParser;
    private EventFactory             $eventFactory;

    protected function setUp(): void
    {
        $this->parser       = new FrameParser();
        $this->classifier   = new InboundMessageClassifier();
        $this->replyFactory = new ReplyFactory();
        $this->eventParser  = new EventParser();
        $this->eventFactory = new EventFactory();
    }

    // ---------------------------------------------------------------------------
    // Session identity attachment
    // ---------------------------------------------------------------------------

    public function test_context_carries_provided_session_id(): void
    {
        $sessionId = ConnectionSessionId::fromString('test-session-1');
        $context   = new CorrelationContext($sessionId);

        $this->assertTrue($context->sessionId()->equals($sessionId));
    }

    public function test_metadata_carries_session_id(): void
    {
        $sessionId = ConnectionSessionId::fromString('test-session-1');
        $context   = new CorrelationContext($sessionId);

        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertNotNull($metadata->sessionId());
        $this->assertTrue($metadata->sessionId()->equals($sessionId));
    }

    public function test_event_envelope_carries_session_id(): void
    {
        $sessionId = ConnectionSessionId::fromString('session-42');
        $context   = new CorrelationContext($sessionId);

        $event    = $this->makeChannelCreateEvent();
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertNotNull($envelope->sessionId());
        $this->assertTrue($envelope->sessionId()->equals($sessionId));
    }

    public function test_reply_envelope_carries_session_id(): void
    {
        $sessionId = ConnectionSessionId::fromString('session-42');
        $context   = new CorrelationContext($sessionId);

        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);

        $this->assertNotNull($envelope->sessionId());
        $this->assertTrue($envelope->sessionId()->equals($sessionId));
    }

    // ---------------------------------------------------------------------------
    // Observation ordering — sequence is monotonically increasing
    // ---------------------------------------------------------------------------

    public function test_sequence_starts_at_one_for_first_reply(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertSame(1, $metadata->observationSequence()->position());
    }

    public function test_sequence_increases_with_each_call(): void
    {
        $context = CorrelationContext::anonymous();

        $reply = $this->makeBgapiReply();
        $m1    = $context->nextMetadataForReply($reply);

        $event = $this->makeChannelCreateEvent();
        $m2    = $context->nextMetadataForEvent($event);

        $m3 = $context->nextMetadataForReply($reply);

        $this->assertSame(1, $m1->observationSequence()->position());
        $this->assertSame(2, $m2->observationSequence()->position());
        $this->assertSame(3, $m3->observationSequence()->position());
    }

    public function test_reply_envelope_sequence_is_ordered(): void
    {
        $context = CorrelationContext::anonymous();

        $reply = $this->makeBgapiReply();
        $e1    = new ReplyEnvelope($reply, $context->nextMetadataForReply($reply));
        $e2    = new ReplyEnvelope($reply, $context->nextMetadataForReply($reply));

        $this->assertTrue($e2->observationSequence()->isAfter($e1->observationSequence()));
    }

    public function test_event_envelope_sequence_is_ordered(): void
    {
        $context = CorrelationContext::anonymous();

        $event = $this->makeChannelCreateEvent();
        $e1    = new EventEnvelope($event, $context->nextMetadataForEvent($event));
        $e2    = new EventEnvelope($event, $context->nextMetadataForEvent($event));

        $this->assertTrue($e2->observationSequence()->isAfter($e1->observationSequence()));
    }

    // ---------------------------------------------------------------------------
    // bgapi lineage — BgapiAcceptedReply ↔ BackgroundJobEvent via Job-UUID
    // ---------------------------------------------------------------------------

    public function test_bgapi_reply_carries_job_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply(self::JOB_UUID);
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertTrue($metadata->hasJobCorrelation());
        $this->assertSame(self::JOB_UUID, $metadata->jobCorrelation()->jobUuid());
    }

    public function test_background_job_event_carries_matching_job_correlation(): void
    {
        $context   = CorrelationContext::anonymous();
        $bgJobEvent = $this->makeBackgroundJobEvent(self::JOB_UUID);
        $metadata  = $context->nextMetadataForEvent($bgJobEvent);

        $this->assertTrue($metadata->hasJobCorrelation());
        $this->assertSame(self::JOB_UUID, $metadata->jobCorrelation()->jobUuid());
    }

    public function test_bgapi_reply_and_bg_job_event_share_same_job_uuid(): void
    {
        $context = CorrelationContext::anonymous();

        // Step 1: bgapi command is accepted → reply carries Job-UUID
        $reply         = $this->makeBgapiReply(self::JOB_UUID);
        $replyMetadata = $context->nextMetadataForReply($reply);

        // Step 2: BACKGROUND_JOB event arrives — carries same Job-UUID
        $bgJobEvent   = $this->makeBackgroundJobEvent(self::JOB_UUID);
        $eventMetadata = $context->nextMetadataForEvent($bgJobEvent);

        // Both carry the same Job-UUID → upper layers can correlate them
        $this->assertNotNull($replyMetadata->jobCorrelation());
        $this->assertNotNull($eventMetadata->jobCorrelation());
        $this->assertTrue(
            $replyMetadata->jobCorrelation()->equals($eventMetadata->jobCorrelation())
        );
    }

    public function test_non_bgapi_reply_carries_no_job_correlation(): void
    {
        $context = CorrelationContext::anonymous();

        $this->parser->feed(EslFixtureBuilder::authAccepted());
        $frames     = $this->parser->drain();
        $classified = $this->classifier->classify($frames[0]);
        $reply      = $this->replyFactory->fromClassified($classified);

        $metadata = $context->nextMetadataForReply($reply);

        $this->assertFalse($metadata->hasJobCorrelation());
        $this->assertNull($metadata->jobCorrelation());
    }

    public function test_channel_event_without_job_uuid_carries_no_job_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeChannelCreateEvent();
        $metadata = $context->nextMetadataForEvent($event);

        $this->assertFalse($metadata->hasJobCorrelation());
        $this->assertNull($metadata->jobCorrelation());
    }

    // ---------------------------------------------------------------------------
    // Partial channel correlation — missing identifiers do not crash
    // ---------------------------------------------------------------------------

    public function test_channel_create_event_provides_full_channel_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeChannelCreateEvent(self::CHANNEL_UUID);
        $metadata = $context->nextMetadataForEvent($event);

        $this->assertTrue($metadata->hasChannelCorrelation());

        $corr = $metadata->channelCorrelation();
        $this->assertSame(self::CHANNEL_UUID, $corr->uniqueId());
        $this->assertNotNull($corr->channelName());
        $this->assertNotNull($corr->callDirection());
    }

    public function test_background_job_event_has_no_channel_correlation(): void
    {
        // BACKGROUND_JOB events do not carry Unique-ID
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeBackgroundJobEvent(self::JOB_UUID);
        $metadata = $context->nextMetadataForEvent($event);

        // No channel context expected for bgapi job events
        $this->assertFalse($metadata->hasChannelCorrelation());
        $this->assertNull($metadata->channelCorrelation());
    }

    public function test_reply_carries_no_channel_correlation(): void
    {
        // Replies do not carry channel context in the ESL wire protocol
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertFalse($metadata->hasChannelCorrelation());
        $this->assertNull($metadata->channelCorrelation());
    }

    // ---------------------------------------------------------------------------
    // Protocol sequence preservation
    // ---------------------------------------------------------------------------

    public function test_event_metadata_preserves_protocol_sequence(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeChannelCreateEvent();
        $metadata = $context->nextMetadataForEvent($event);

        // The CHANNEL_CREATE fixture includes Event-Sequence: 12345
        $this->assertSame('12345', $metadata->protocolSequence());
    }

    public function test_reply_metadata_has_null_protocol_sequence(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertNull($metadata->protocolSequence());
    }

    // ---------------------------------------------------------------------------
    // Envelope composition — typed objects + metadata accessible
    // ---------------------------------------------------------------------------

    public function test_event_envelope_exposes_original_event(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeChannelCreateEvent();
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertSame($event, $envelope->event());
    }

    public function test_reply_envelope_exposes_original_reply(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);

        $this->assertSame($reply, $envelope->reply());
    }

    public function test_event_envelope_convenience_passthrough_job_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeBackgroundJobEvent(self::JOB_UUID);
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertNotNull($envelope->jobCorrelation());
        $this->assertSame(self::JOB_UUID, $envelope->jobCorrelation()->jobUuid());
    }

    public function test_reply_envelope_convenience_passthrough_job_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply(self::JOB_UUID);
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);

        $this->assertNotNull($envelope->jobCorrelation());
        $this->assertSame(self::JOB_UUID, $envelope->jobCorrelation()->jobUuid());
    }

    public function test_event_envelope_convenience_passthrough_channel_correlation(): void
    {
        $context  = CorrelationContext::anonymous();
        $event    = $this->makeChannelCreateEvent(self::CHANNEL_UUID);
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertNotNull($envelope->channelCorrelation());
        $this->assertSame(self::CHANNEL_UUID, $envelope->channelCorrelation()->uniqueId());
    }

    // ---------------------------------------------------------------------------
    // ReplayEnvelopeFactory::withSession integration
    // ---------------------------------------------------------------------------

    public function test_replay_envelope_factory_with_session_preserves_session_id(): void
    {
        $sessionId     = ConnectionSessionId::fromString('replay-session-1');
        $replayFactory = ReplayEnvelopeFactory::withSession($sessionId);

        $event    = $this->makeChannelCreateEvent();
        $envelope = $replayFactory->fromEvent($event);

        $this->assertSame('replay-session-1', $envelope->sessionId());
    }

    public function test_correlation_context_and_replay_factory_share_session_id(): void
    {
        $sessionId = ConnectionSessionId::generate();
        $context   = new CorrelationContext($sessionId);

        // ReplayEnvelopeFactory can be bound to the same session
        $replayFactory = ReplayEnvelopeFactory::withSession($context->sessionId());

        $event          = $this->makeChannelCreateEvent();
        $replayEnvelope = $replayFactory->fromEvent($event);

        $this->assertSame($context->sessionId()->toString(), $replayEnvelope->sessionId());
    }

    // ---------------------------------------------------------------------------
    // Timestamp presence
    // ---------------------------------------------------------------------------

    public function test_metadata_observed_at_micros_is_non_zero(): void
    {
        $context  = CorrelationContext::anonymous();
        $reply    = $this->makeBgapiReply();
        $metadata = $context->nextMetadataForReply($reply);

        $this->assertGreaterThan(0, $metadata->observedAtMicros());
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function makeBgapiReply(string $jobUuid = self::JOB_UUID): BgapiAcceptedReply
    {
        $this->parser->reset();
        $this->parser->feed(EslFixtureBuilder::bgapiAccepted($jobUuid));
        $frames     = $this->parser->drain();
        $classified = $this->classifier->classify($frames[0]);
        $reply      = $this->replyFactory->fromClassified($classified);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);
        return $reply;
    }

    private function makeChannelCreateEvent(string $uniqueId = self::CHANNEL_UUID): \Apntalk\EslCore\Events\NormalizedEvent
    {
        $this->parser->reset();
        $this->parser->feed(EslFixtureBuilder::channelCreateEvent($uniqueId));
        $frames = $this->parser->drain();

        return $this->eventParser->parse($frames[0]);
    }

    private function makeBackgroundJobEvent(string $jobUuid = self::JOB_UUID): BackgroundJobEvent
    {
        $this->parser->reset();
        $this->parser->feed(EslFixtureBuilder::backgroundJobEvent($jobUuid));
        $frames     = $this->parser->drain();
        $normalized = $this->eventParser->parse($frames[0]);
        $typed      = $this->eventFactory->fromNormalized($normalized);

        $this->assertInstanceOf(BackgroundJobEvent::class, $typed);
        return $typed;
    }
}
