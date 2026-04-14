<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    private FrameParser $parser;
    private InboundMessageClassifier $classifier;
    private ReplyFactory $replyFactory;
    private EventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->parser = new FrameParser();
        $this->classifier = new InboundMessageClassifier();
        $this->replyFactory = new ReplyFactory();
        $this->eventFactory = new EventFactory();
    }

    public function test_command_reply_smoke_path_is_coherent(): void
    {
        $command = new AuthCommand('ClueCon');
        $sessionId = ConnectionSessionId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa');
        $correlation = new CorrelationContext($sessionId);
        $replay = ReplayEnvelopeFactory::withSession($sessionId);

        $this->assertSame("auth ClueCon\n\n", $command->serialize());

        $reply = $this->parseReplyFixture(EslFixtureBuilder::authAccepted(), InboundMessageCategory::AuthAccepted);
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

        $reply = $this->parseReplyFixture(
            EslFixtureBuilder::bgapiAccepted(),
            InboundMessageCategory::BgapiAccepted
        );
        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);

        $replyMetadata = $correlation->nextMetadataForReply($reply);
        $replyEnvelope = new ReplyEnvelope($reply, $replyMetadata);
        $replyReplay = $replay->fromReplyEnvelope($replyEnvelope);

        $event = $this->parseEventFixture(EslFixtureBuilder::backgroundJobEvent());
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

    private function parseReplyFixture(string $fixture, InboundMessageCategory $expectedCategory): \Apntalk\EslCore\Contracts\ReplyInterface
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);

        $classified = $this->classifier->classify($frames[0]);
        $this->assertSame($expectedCategory, $classified->category);

        return $this->replyFactory->fromClassified($classified);
    }

    private function parseEventFixture(string $fixture): \Apntalk\EslCore\Contracts\EventInterface
    {
        $this->parser->reset();
        $this->parser->feed($fixture);
        $frames = $this->parser->drain();

        $this->assertCount(1, $frames);

        $classified = $this->classifier->classify($frames[0]);
        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);

        return $this->eventFactory->fromFrame($frames[0]);
    }
}
