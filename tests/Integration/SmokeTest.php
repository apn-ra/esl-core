<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    private InboundPipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = InboundPipeline::withDefaults();
    }

    public function test_command_reply_smoke_path_is_coherent(): void
    {
        $command = new AuthCommand('ClueCon');
        $sessionId = ConnectionSessionId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $correlation = new CorrelationContext($sessionId);
        $replay = ReplayEnvelopeFactory::withSession($sessionId);

        $this->assertSame("auth ClueCon\n\n", $command->serialize());

        $message = $this->pipeline->decode(EslFixtureBuilder::authAccepted())[0];
        $this->assertSame(InboundMessageType::Reply, $message->type());
        $reply = $message->reply();
        $this->assertInstanceOf(AuthAcceptedReply::class, $reply);

        $metadata = $correlation->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);
        $replayEnvelope = $replay->fromReplyEnvelope($envelope);

        $this->assertSame($sessionId->toString(), $envelope->sessionId()?->toString());
        $this->assertSame(1, $envelope->observationSequence()->position());
        $this->assertSame('AuthAcceptedReply', $replayEnvelope->capturedName());
        $this->assertSame(1, $replayEnvelope->captureSequence());
        $this->assertSame('command/reply', $replayEnvelope->protocolFacts()['content-type']);
        $this->assertSame('1', $replayEnvelope->derivedMetadata()['observation-sequence']);
    }

    public function test_async_event_smoke_path_preserves_typed_lineage_and_metadata(): void
    {
        $sessionId = ConnectionSessionId::fromString('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb');
        $correlation = new CorrelationContext($sessionId);
        $replay = ReplayEnvelopeFactory::withSession($sessionId);

        $replyMessage = $this->pipeline->decode(EslFixtureBuilder::bgapiAccepted())[0];
        $this->assertSame(InboundMessageType::Reply, $replyMessage->type());
        $reply = $replyMessage->reply();
        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);

        $replyMetadata = $correlation->nextMetadataForReply($reply);
        $replyEnvelope = new ReplyEnvelope($reply, $replyMetadata);
        $replyReplay = $replay->fromReplyEnvelope($replyEnvelope);

        $eventMessage = $this->pipeline->decode(EslFixtureBuilder::backgroundJobEvent())[0];
        $this->assertSame(InboundMessageType::Event, $eventMessage->type());
        $event = $eventMessage->event();
        $this->assertInstanceOf(BackgroundJobEvent::class, $event);

        $eventMetadata = $correlation->nextMetadataForEvent($event);
        $eventEnvelope = new EventEnvelope($event, $eventMetadata);
        $eventReplay = $replay->fromEventEnvelope($eventEnvelope);

        $this->assertSame($reply->jobUuid(), $event->jobUuid());
        $this->assertSame($reply->jobUuid(), $replyEnvelope->jobCorrelation()?->jobUuid());
        $this->assertSame($event->jobUuid(), $eventEnvelope->jobCorrelation()?->jobUuid());
        $this->assertSame(1, $replyEnvelope->observationSequence()->position());
        $this->assertSame(2, $eventEnvelope->observationSequence()->position());
        $this->assertSame(1, $replyReplay->captureSequence());
        $this->assertSame(2, $eventReplay->captureSequence());
        $this->assertSame('BACKGROUND_JOB', $eventReplay->capturedName());
        $this->assertSame($event->jobUuid(), $eventReplay->protocolFacts()['job-uuid']);
        $this->assertSame('2', $eventReplay->derivedMetadata()['observation-sequence']);
    }
}
