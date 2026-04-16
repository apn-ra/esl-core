<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\ChannelLifecycleEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Transport\InMemoryTransport;
use PHPUnit\Framework\TestCase;

/**
 * Integration coverage for advanced low-level composition on top of the public
 * in-memory transport.
 *
 * The preferred downstream ingress path is still InboundPipeline; this class
 * exists to harden the lower-level parser/classifier/reply/event composition
 * that remains available for targeted fixture-backed work.
 */
final class InMemoryTransportPipelineTest extends TestCase
{
    private const JOB_UUID = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';

    private InMemoryTransport $transport;
    private FrameParser $parser;
    private InboundMessageClassifier $classifier;
    private ReplyFactory $replyFactory;
    private EventFactory $eventFactory;
    private CorrelationContext $correlation;
    private ReplayEnvelopeFactory $replay;
    private ConnectionSessionId $sessionId;
    /** @var list<\Apntalk\EslCore\Protocol\Frame> */
    private array $pendingFrames = [];

    protected function setUp(): void
    {
        $this->transport = new InMemoryTransport();
        $this->parser = new FrameParser();
        $this->classifier = new InboundMessageClassifier();
        $this->replyFactory = new ReplyFactory();
        $this->eventFactory = new EventFactory();
        $this->sessionId = ConnectionSessionId::fromString('11111111-1111-4111-8111-111111111111');
        $this->correlation = new CorrelationContext($this->sessionId);
        $this->replay = ReplayEnvelopeFactory::withSession($this->sessionId);
    }

    public function test_auth_reply_flow_runs_end_to_end_through_replay_capture(): void
    {
        $this->transport->enqueueInbound(EslFixtureBuilder::authAccepted());

        [$classified, $reply] = $this->consumeReply();
        $metadata = $this->correlation->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);
        $replayEnvelope = $this->replay->fromReplyEnvelope($envelope);

        $this->assertSame(InboundMessageCategory::AuthAccepted, $classified->category);
        $this->assertInstanceOf(AuthAcceptedReply::class, $reply);
        $this->assertSame($this->sessionId->toString(), $envelope->sessionId()?->toString());
        $this->assertSame(1, $envelope->observationSequence()->position());
        $this->assertSame(1, $replayEnvelope->captureSequence());
        $this->assertSame($this->sessionId->toString(), $replayEnvelope->sessionId());
        $this->assertSame('command/reply', $replayEnvelope->protocolFacts()['content-type']);
        $this->assertSame('1', $replayEnvelope->derivedMetadata()['observation-sequence']);
    }

    public function test_api_command_reply_flow_preserves_deterministic_metadata(): void
    {
        $this->transport->enqueueInbound(EslFixtureBuilder::apiResponse("+OK status\n"));

        [$classified, $reply] = $this->consumeReply();
        $metadata = $this->correlation->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);
        $replayEnvelope = $this->replay->fromReplyEnvelope($envelope);

        $this->assertSame(InboundMessageCategory::ApiResponse, $classified->category);
        $this->assertInstanceOf(ApiReply::class, $reply);
        $this->assertSame('+OK status', $reply->trimmedBody());
        $this->assertSame($this->sessionId->toString(), $envelope->sessionId()?->toString());
        $this->assertSame(1, $envelope->observationSequence()->position());
        $this->assertSame(1, $replayEnvelope->captureSequence());
        $this->assertNull($replayEnvelope->protocolSequence());
        $this->assertSame('api/response', $replayEnvelope->protocolFacts()['content-type']);
        $this->assertSame('1', $replayEnvelope->derivedMetadata()['observation-sequence']);
    }

    public function test_reply_factory_from_frame_matches_existing_low_level_reply_path(): void
    {
        $this->transport->enqueueInbound(EslFixtureBuilder::bgapiAccepted(self::JOB_UUID));

        $classified = $this->consumeClassified();
        $fromClassified = $this->replyFactory->fromClassified($classified);
        $fromFrame = $this->replyFactory->fromFrame($classified->frame, $this->classifier);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $fromClassified);
        $this->assertInstanceOf(BgapiAcceptedReply::class, $fromFrame);
        $this->assertSame($fromClassified::class, $fromFrame::class);
        $this->assertSame($fromClassified->frame()->replyText(), $fromFrame->frame()->replyText());
    }

    public function test_bgapi_acceptance_and_background_job_flow_preserve_shared_correlation(): void
    {
        $this->transport->enqueueInbound(EslFixtureBuilder::bgapiAccepted(self::JOB_UUID));
        $this->transport->enqueueInbound(EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID, "+OK queued\n"));

        [$replyClassified, $reply] = $this->consumeReply();
        $replyMetadata = $this->correlation->nextMetadataForReply($reply);
        $replyEnvelope = new ReplyEnvelope($reply, $replyMetadata);
        $replyReplay = $this->replay->fromReplyEnvelope($replyEnvelope);

        [$eventClassified, $event] = $this->consumeEvent();
        $eventMetadata = $this->correlation->nextMetadataForEvent($event);
        $eventEnvelope = new EventEnvelope($event, $eventMetadata);
        $eventReplay = $this->replay->fromEventEnvelope($eventEnvelope);

        $this->assertSame(InboundMessageCategory::BgapiAccepted, $replyClassified->category);
        $this->assertSame(InboundMessageCategory::EventMessage, $eventClassified->category);
        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);
        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
        $this->assertSame(self::JOB_UUID, $reply->jobUuid());
        $this->assertSame(self::JOB_UUID, $eventEnvelope->jobCorrelation()?->jobUuid());
        $this->assertSame(self::JOB_UUID, $replyReplay->protocolFacts()['job-uuid']);
        $this->assertSame(self::JOB_UUID, $replyReplay->derivedMetadata()['job-correlation.job-uuid']);
        $this->assertSame(self::JOB_UUID, $eventReplay->protocolFacts()['job-uuid']);
        $this->assertSame(self::JOB_UUID, $eventReplay->derivedMetadata()['job-correlation.job-uuid']);
        $this->assertSame(1, $replyEnvelope->observationSequence()->position());
        $this->assertSame(2, $eventEnvelope->observationSequence()->position());
        $this->assertSame(1, $replyReplay->captureSequence());
        $this->assertSame(2, $eventReplay->captureSequence());
    }

    public function test_unsolicited_event_flow_remains_truthful_without_command_context(): void
    {
        $this->transport->enqueueInbound(EslFixtureBuilder::channelCreateEvent());

        [$classified, $event] = $this->consumeEvent();
        $metadata = $this->correlation->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replayEnvelope = $this->replay->fromEventEnvelope($envelope);

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
        $this->assertInstanceOf(ChannelLifecycleEvent::class, $event);
        $this->assertNotNull($envelope->channelCorrelation());
        $this->assertNull($envelope->jobCorrelation());
        $this->assertSame('CHANNEL_CREATE', $replayEnvelope->protocolFacts()['event-name']);
        $this->assertArrayNotHasKey('job-correlation.job-uuid', $replayEnvelope->derivedMetadata());
        $this->assertArrayHasKey('channel-correlation.unique-id', $replayEnvelope->derivedMetadata());
    }

    public function test_unknown_event_degrades_to_raw_event_without_breaking_replay(): void
    {
        $fixture = EslFixtureBuilder::eventPlain(
            EslFixtureBuilder::eventData([
                'Event-Name' => 'TOTALLY_UNKNOWN_EVENT_XYZ',
                'Event-Sequence' => '22222',
                'Event-Date-Timestamp' => '1482422209000000',
            ])
        );
        $this->transport->enqueueInbound($fixture);

        [$classified, $event] = $this->consumeEvent();
        $metadata = $this->correlation->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replayEnvelope = $this->replay->fromEventEnvelope($envelope);

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
        $this->assertInstanceOf(RawEvent::class, $event);
        $this->assertSame('TOTALLY_UNKNOWN_EVENT_XYZ', $event->eventName());
        $this->assertSame('TOTALLY_UNKNOWN_EVENT_XYZ', $replayEnvelope->capturedName());
        $this->assertSame('22222', $replayEnvelope->protocolSequence());
        $this->assertSame('1482422209000000', $replayEnvelope->protocolFacts()['event-date-timestamp']);
        $this->assertSame('1', $replayEnvelope->derivedMetadata()['observation-sequence']);
    }

    /**
     * @return array{ClassifiedInboundMessage, ReplyInterface}
     */
    private function consumeReply(): array
    {
        $classified = $this->consumeClassified();
        $reply = $this->replyFactory->fromClassified($classified);

        return [$classified, $reply];
    }

    /**
     * @return array{ClassifiedInboundMessage, EventInterface}
     */
    private function consumeEvent(): array
    {
        $classified = $this->consumeClassified();
        $event = $this->eventFactory->fromFrame($classified->frame);

        return [$classified, $event];
    }

    private function consumeClassified(): ClassifiedInboundMessage
    {
        if ($this->pendingFrames === []) {
            $this->parser->feed($this->readAllInbound());
            $this->pendingFrames = $this->parser->drain();
        }

        $this->assertNotEmpty($this->pendingFrames);

        $frame = array_shift($this->pendingFrames);
        $this->assertNotNull($frame);

        return $this->classifier->classify($frame);
    }

    private function readAllInbound(): string
    {
        $buffer = '';

        while ($this->transport->pendingInboundBytes() > 0) {
            $chunk = $this->transport->read(4096);
            $this->assertNotNull($chunk);
            $buffer .= $chunk;
        }

        return $buffer;
    }
}
