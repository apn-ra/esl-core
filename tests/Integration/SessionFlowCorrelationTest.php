<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Commands\EventSubscriptionCommand;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\ChannelLifecycleEvent;
use Apntalk\EslCore\Events\HangupEvent;
use Apntalk\EslCore\Inbound\DecodedInboundMessage;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\CommandReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for the InboundPipeline + CorrelationContext composition.
 *
 * These tests verify that both public-facing objects compose correctly across
 * a realistic session flow. No internal classifier, parser, or factory types
 * are used — only the public-facing contract surfaces.
 */
final class SessionFlowCorrelationTest extends TestCase
{
    private const SESSION_ID   = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
    private const CHANNEL_UUID = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78';
    private const JOB_UUID     = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';

    // ---------------------------------------------------------------------------
    // Full session sequence: auth → subscribe → channel create → job → hangup
    // ---------------------------------------------------------------------------

    public function test_full_session_sequence_observation_numbers_are_monotonically_correct(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        // Feed all five frames at once — validates that the pipeline handles
        // concatenated frames correctly, as happens on real transports.
        $pipeline->push(
            EslFixtureBuilder::authAccepted() .
            EslFixtureBuilder::commandReplyOk('+OK Events Enabled') .
            EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID) .
            EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID) .
            EslFixtureBuilder::hangupEvent(self::CHANNEL_UUID)
        );
        $messages = $pipeline->drain();

        $this->assertCount(5, $messages);

        // Build correlation envelopes for each message in order
        [$auth, $sub, $create, $job, $hangup] = $messages;

        $authEnv   = new ReplyEnvelope($auth->reply(), $context->nextMetadataForReply($auth->reply()));
        $subEnv    = new ReplyEnvelope($sub->reply(), $context->nextMetadataForReply($sub->reply()));
        $createEnv = new EventEnvelope($create->event(), $context->nextMetadataForEvent($create->event()));
        $jobEnv    = new EventEnvelope($job->event(), $context->nextMetadataForEvent($job->event()));
        $hangupEnv = new EventEnvelope($hangup->event(), $context->nextMetadataForEvent($hangup->event()));

        // Observation sequences must be 1-5 in order
        $this->assertSame(1, $authEnv->observationSequence()->position());
        $this->assertSame(2, $subEnv->observationSequence()->position());
        $this->assertSame(3, $createEnv->observationSequence()->position());
        $this->assertSame(4, $jobEnv->observationSequence()->position());
        $this->assertSame(5, $hangupEnv->observationSequence()->position());
    }

    public function test_full_session_sequence_session_id_is_consistent_across_all_observations(): void
    {
        $sessionId = ConnectionSessionId::fromString(self::SESSION_ID);
        $pipeline  = new InboundPipeline();
        $context   = new CorrelationContext($sessionId);

        $pipeline->push(
            EslFixtureBuilder::authAccepted() .
            EslFixtureBuilder::commandReplyOk('+OK Events Enabled') .
            EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID) .
            EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID) .
            EslFixtureBuilder::hangupEvent(self::CHANNEL_UUID)
        );
        $messages = $pipeline->drain();

        [$auth, $sub, $create, $job, $hangup] = $messages;

        $envelopes = [
            new ReplyEnvelope($auth->reply(), $context->nextMetadataForReply($auth->reply())),
            new ReplyEnvelope($sub->reply(), $context->nextMetadataForReply($sub->reply())),
            new EventEnvelope($create->event(), $context->nextMetadataForEvent($create->event())),
            new EventEnvelope($job->event(), $context->nextMetadataForEvent($job->event())),
            new EventEnvelope($hangup->event(), $context->nextMetadataForEvent($hangup->event())),
        ];

        foreach ($envelopes as $i => $envelope) {
            $this->assertNotNull(
                $envelope->sessionId(),
                "Observation #{$i} must carry session ID"
            );
            $this->assertTrue(
                $envelope->sessionId()->equals($sessionId),
                "Observation #{$i} session ID must match the context session ID"
            );
        }
    }

    public function test_full_session_sequence_typed_decoding_is_correct_for_each_step(): void
    {
        $pipeline = new InboundPipeline();

        $pipeline->push(
            EslFixtureBuilder::authAccepted() .
            EslFixtureBuilder::commandReplyOk('+OK Events Enabled') .
            EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID) .
            EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID) .
            EslFixtureBuilder::hangupEvent(self::CHANNEL_UUID)
        );
        $messages = $pipeline->drain();

        $this->assertCount(5, $messages);

        [$auth, $sub, $create, $job, $hangup] = $messages;

        $this->assertSame(InboundMessageType::Reply, $auth->type());
        $this->assertInstanceOf(AuthAcceptedReply::class, $auth->reply());

        $this->assertSame(InboundMessageType::Reply, $sub->type());
        $this->assertInstanceOf(CommandReply::class, $sub->reply());

        $this->assertSame(InboundMessageType::Event, $create->type());
        $this->assertInstanceOf(ChannelLifecycleEvent::class, $create->event());

        $this->assertSame(InboundMessageType::Event, $job->type());
        $this->assertInstanceOf(BackgroundJobEvent::class, $job->event());

        $this->assertSame(InboundMessageType::Event, $hangup->type());
        $this->assertInstanceOf(HangupEvent::class, $hangup->event());
    }

    // ---------------------------------------------------------------------------
    // Correlation: channel events carry channel correlation through the pipeline
    // ---------------------------------------------------------------------------

    public function test_channel_create_event_carries_channel_correlation_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID));
        $this->assertCount(1, $messages);

        $event    = $messages[0]->event();
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertTrue($envelope->metadata()->hasChannelCorrelation());
        $this->assertNotNull($envelope->channelCorrelation());
        $this->assertSame(self::CHANNEL_UUID, $envelope->channelCorrelation()->uniqueId());
        $this->assertNull($envelope->jobCorrelation());
    }

    public function test_hangup_event_carries_channel_correlation_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(
            EslFixtureBuilder::hangupEvent(self::CHANNEL_UUID, 'NORMAL_CLEARING')
        );
        $this->assertCount(1, $messages);

        $event    = $messages[0]->event();
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertTrue($envelope->metadata()->hasChannelCorrelation());
        $this->assertSame(self::CHANNEL_UUID, $envelope->channelCorrelation()?->uniqueId());
        $this->assertNull($envelope->jobCorrelation());
    }

    // ---------------------------------------------------------------------------
    // Correlation: background job event carries job correlation through the pipeline
    // ---------------------------------------------------------------------------

    public function test_background_job_event_carries_job_correlation_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID));
        $this->assertCount(1, $messages);

        $event    = $messages[0]->event();
        $metadata = $context->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);

        $this->assertTrue($envelope->metadata()->hasJobCorrelation());
        $this->assertSame(self::JOB_UUID, $envelope->jobCorrelation()?->jobUuid());
        $this->assertNull($envelope->channelCorrelation());
    }

    // ---------------------------------------------------------------------------
    // Correlation: non-bgapi replies carry no correlation through the pipeline
    // ---------------------------------------------------------------------------

    public function test_auth_accepted_reply_carries_no_correlation_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::authAccepted());
        $reply    = $messages[0]->reply();
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);

        $this->assertFalse($envelope->metadata()->hasJobCorrelation());
        $this->assertFalse($envelope->metadata()->hasChannelCorrelation());
        $this->assertNull($envelope->jobCorrelation());
    }

    public function test_subscription_accepted_reply_carries_no_correlation_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::commandReplyOk('+OK Events Enabled'));
        $reply    = $messages[0]->reply();
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);

        $this->assertFalse($envelope->metadata()->hasJobCorrelation());
        $this->assertFalse($envelope->metadata()->hasChannelCorrelation());
    }

    // ---------------------------------------------------------------------------
    // Protocol sequence: FreeSWITCH Event-Sequence is captured through the pipeline
    // ---------------------------------------------------------------------------

    public function test_channel_event_protocol_sequence_is_preserved_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID));
        $event    = $messages[0]->event();
        $metadata = $context->nextMetadataForEvent($event);

        // EslFixtureBuilder::channelCreateEvent uses Event-Sequence: 12345
        $this->assertSame('12345', $metadata->protocolSequence());
    }

    public function test_background_job_event_protocol_sequence_is_preserved_through_pipeline(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID));
        $event    = $messages[0]->event();
        $metadata = $context->nextMetadataForEvent($event);

        // EslFixtureBuilder::backgroundJobEvent uses Event-Sequence: 12346
        $this->assertSame('12346', $metadata->protocolSequence());
    }

    public function test_replies_carry_null_protocol_sequence(): void
    {
        $pipeline = new InboundPipeline();
        $context  = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        $messages = $pipeline->decode(EslFixtureBuilder::authAccepted());
        $reply    = $messages[0]->reply();
        $metadata = $context->nextMetadataForReply($reply);

        // Replies do not carry a FreeSWITCH Event-Sequence header
        $this->assertNull($metadata->protocolSequence());
    }

    // ---------------------------------------------------------------------------
    // Transport integration: pipeline receives bytes from InMemoryTransport
    // ---------------------------------------------------------------------------

    public function test_session_flow_read_from_transport_produces_same_result_as_direct_push(): void
    {
        $transport = new InMemoryTransport();
        $pipeline  = new InboundPipeline();
        $context   = new CorrelationContext(ConnectionSessionId::fromString(self::SESSION_ID));

        // Enqueue the session frames into the transport as a single block
        $transport->enqueueInbound(
            EslFixtureBuilder::authAccepted() .
            EslFixtureBuilder::commandReplyOk('+OK Events Enabled') .
            EslFixtureBuilder::channelCreateEvent(self::CHANNEL_UUID) .
            EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID) .
            EslFixtureBuilder::hangupEvent(self::CHANNEL_UUID)
        );

        // Read all bytes from transport and push into pipeline
        while ($transport->pendingInboundBytes() > 0) {
            $chunk = $transport->read(4096);
            $this->assertNotNull($chunk);
            $pipeline->push($chunk);
        }
        $messages = $pipeline->drain();

        $this->assertCount(5, $messages);

        [$auth, $sub, $create, $job, $hangup] = $messages;

        $authEnv    = new ReplyEnvelope($auth->reply(), $context->nextMetadataForReply($auth->reply()));
        $subEnv     = new ReplyEnvelope($sub->reply(), $context->nextMetadataForReply($sub->reply()));
        $createEnv  = new EventEnvelope($create->event(), $context->nextMetadataForEvent($create->event()));
        $jobEnv     = new EventEnvelope($job->event(), $context->nextMetadataForEvent($job->event()));
        $hangupEnv  = new EventEnvelope($hangup->event(), $context->nextMetadataForEvent($hangup->event()));

        // Observation sequence is monotonic
        $this->assertSame(1, $authEnv->observationSequence()->position());
        $this->assertSame(2, $subEnv->observationSequence()->position());
        $this->assertSame(3, $createEnv->observationSequence()->position());
        $this->assertSame(4, $jobEnv->observationSequence()->position());
        $this->assertSame(5, $hangupEnv->observationSequence()->position());

        // Correlation is populated for the right types only
        $this->assertFalse($authEnv->metadata()->hasChannelCorrelation());
        $this->assertFalse($subEnv->metadata()->hasChannelCorrelation());
        $this->assertTrue($createEnv->metadata()->hasChannelCorrelation());
        $this->assertTrue($jobEnv->metadata()->hasJobCorrelation());
        $this->assertTrue($hangupEnv->metadata()->hasChannelCorrelation());

        // Channel UUID is consistent for the same channel across events
        $this->assertSame(
            $createEnv->channelCorrelation()?->uniqueId(),
            $hangupEnv->channelCorrelation()?->uniqueId()
        );

        // Write path: client commands produce expected wire bytes on the transport
        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $transport->write(EventSubscriptionCommand::all()->serialize());
        $this->assertSame(
            "auth ClueCon\n\nevent plain all\n\n",
            $transport->drainOutbound()
        );
    }
}
