<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Replay;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Exceptions\ReplayConsistencyException;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class ReplayEnvelopeFactoryTest extends TestCase
{
    public function test_from_reply_envelope_uses_correlation_sequence_and_timestamp(): void
    {
        $sessionId = ConnectionSessionId::fromString('22222222-2222-4222-8222-222222222222');
        $context = new CorrelationContext($sessionId);
        $factory = ReplayEnvelopeFactory::withSession($sessionId);
        $reply = $this->makeBgapiReply();

        $metadata = $context->nextMetadataForReply($reply);
        $envelope = $factory->fromReplyEnvelope(new ReplyEnvelope($reply, $metadata));

        $this->assertSame(1, $envelope->captureSequence());
        $this->assertSame($metadata->observedAtMicros(), $envelope->capturedAtMicros());
        $this->assertSame($sessionId->toString(), $envelope->sessionId());
        $this->assertSame(self::jobUuid(), $envelope->protocolFacts()['job-uuid']);
        $this->assertSame(self::jobUuid(), $envelope->derivedMetadata()['job-correlation.job-uuid']);
    }

    public function test_from_event_envelope_uses_protocol_and_derived_metadata_separately(): void
    {
        $sessionId = ConnectionSessionId::fromString('33333333-3333-4333-8333-333333333333');
        $context = new CorrelationContext($sessionId);
        $factory = ReplayEnvelopeFactory::withSession($sessionId);
        $event = $this->makeBackgroundJobEvent();

        $metadata = $context->nextMetadataForEvent($event);
        $envelope = $factory->fromEventEnvelope(new EventEnvelope($event, $metadata));

        $this->assertSame('12346', $envelope->protocolFacts()['event-sequence']);
        $this->assertSame(self::jobUuid(), $envelope->protocolFacts()['job-uuid']);
        $this->assertSame('1', $envelope->derivedMetadata()['observation-sequence']);
        $this->assertSame(self::jobUuid(), $envelope->derivedMetadata()['job-correlation.job-uuid']);
    }

    public function test_mismatched_factory_and_metadata_session_throws_replay_consistency_exception(): void
    {
        $context = new CorrelationContext(
            ConnectionSessionId::fromString('44444444-4444-4444-8444-444444444444')
        );
        $factory = ReplayEnvelopeFactory::withSession(
            ConnectionSessionId::fromString('55555555-5555-4555-8555-555555555555')
        );
        $reply = $this->makeBgapiReply();
        $envelope = new ReplyEnvelope($reply, $context->nextMetadataForReply($reply));

        $this->expectException(ReplayConsistencyException::class);

        $factory->fromReplyEnvelope($envelope);
    }

    private function makeBgapiReply(): BgapiAcceptedReply
    {
        $parser = new FrameParser();
        $parser->feed(EslFixtureBuilder::bgapiAccepted(self::jobUuid()));
        $frame = $parser->drain()[0];
        $classified = (new InboundMessageClassifier())->classify($frame);
        $reply = (new ReplyFactory())->fromClassified($classified);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);

        return $reply;
    }

    private function makeBackgroundJobEvent(): BackgroundJobEvent
    {
        $parser = new FrameParser();
        $parser->feed(EslFixtureBuilder::backgroundJobEvent(self::jobUuid()));
        $frame = $parser->drain()[0];
        $normalized = (new EventParser())->parse($frame);
        $event = (new EventFactory())->fromNormalized($normalized);

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);

        return $event;
    }

    private static function jobUuid(): string
    {
        return '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
    }
}
