<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Replay;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\ReplayConsistencyException;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Contract coverage for replay-envelope export using lower-level fixture-backed
 * reply/event assembly.
 *
 * These tests harden replay substrate behavior without implying that
 * parser/classifier/reply-factory composition is the preferred ingress path.
 */
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
        $this->assertSame('replay-envelope.v1', $envelope->schemaVersion());
        $this->assertSame($metadata->observedAtMicros(), $envelope->capturedAtMicros());
        $this->assertSame($sessionId->toString(), $envelope->sessionId());
        $this->assertSame(self::jobUuid(), $envelope->protocolFacts()['job-uuid']);
        $this->assertSame(self::jobUuid(), $envelope->derivedMetadata()['job-correlation.job-uuid']);
        $this->assertSame(self::jobUuid(), $envelope->identityFacts()['job-uuid']);
        $this->assertSame('1', $envelope->orderingFacts()['observation-sequence']);
        $this->assertSame(self::jobUuid(), $envelope->causalMetadata()['job-correlation.job-uuid']);
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

    public function test_api_response_reply_raw_payload_preserves_body_bytes_deterministically(): void
    {
        $reply = $this->makeReply(EslFixtureBuilder::apiResponse("+OK status\nUptime: 42\n"));

        $this->assertInstanceOf(ApiReply::class, $reply);

        $first = (new ReplayEnvelopeFactory())->fromReply($reply);
        $second = (new ReplayEnvelopeFactory())->fromReply($reply);

        $expected = "Content-Type: api/response\n"
            . "Content-Length: 22\n"
            . "\n"
            . "+OK status\nUptime: 42\n";

        $this->assertSame($expected, $first->rawPayload());
        $this->assertSame($first->rawPayload(), $second->rawPayload());
    }

    public function test_header_only_reply_raw_payload_keeps_deterministic_header_block_shape(): void
    {
        $reply = $this->makeReply(EslFixtureBuilder::authAccepted());

        $envelope = (new ReplayEnvelopeFactory())->fromReply($reply);

        $this->assertSame(
            "Content-Type: command/reply\nReply-Text: +OK accepted\n\n",
            $envelope->rawPayload(),
        );
    }

    public function test_reply_raw_payload_preserves_interleaved_duplicate_header_order(): void
    {
        $headers = HeaderBag::fromHeaderBlock(
            "Content-Type: command/reply\nX-Debug: first\nReply-Text: +OK accepted\nX-Debug: second"
        );
        $reply = new class (new Frame($headers, '')) implements ReplyInterface {
            public function __construct(
                private readonly Frame $frame,
            ) {}

            public function isSuccess(): bool
            {
                return true;
            }

            public function frame(): Frame
            {
                return $this->frame;
            }
        };

        $envelope = (new ReplayEnvelopeFactory())->fromReply($reply);

        $this->assertSame(
            "Content-Type: command/reply\n"
            . "X-Debug: first\n"
            . "Reply-Text: +OK accepted\n"
            . "X-Debug: second\n\n",
            $envelope->rawPayload(),
        );
    }

    public function test_unnamespaced_custom_reply_class_name_is_preserved(): void
    {
        if (!class_exists('ReplayEnvelopeFactoryUnnamespacedReplyForTest', false)) {
            eval('
                final class ReplayEnvelopeFactoryUnnamespacedReplyForTest implements \Apntalk\EslCore\Contracts\ReplyInterface
                {
                    public function __construct(
                        private readonly \Apntalk\EslCore\Protocol\Frame $frame,
                    ) {}

                    public function isSuccess(): bool
                    {
                        return true;
                    }

                    public function frame(): \Apntalk\EslCore\Protocol\Frame
                    {
                        return $this->frame;
                    }
                }
            ');
        }

        $replyClass = '\ReplayEnvelopeFactoryUnnamespacedReplyForTest';
        $reply = new $replyClass(new Frame(
            HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK custom"),
            '',
        ));

        $envelope = (new ReplayEnvelopeFactory())->fromReply($reply);

        $this->assertSame('ReplayEnvelopeFactoryUnnamespacedReplyForTest', $envelope->capturedName());
    }

    public function test_typed_event_with_explicit_substrate_contract_preserves_normalized_replay_export(): void
    {
        $event = $this->makeBackgroundJobEvent();
        $factory = new ReplayEnvelopeFactory();

        $typedEnvelope = $factory->fromEvent($event);
        $normalizedEnvelope = (new ReplayEnvelopeFactory())->fromNormalizedEvent($event->normalized());

        $this->assertSame($normalizedEnvelope->rawPayload(), $typedEnvelope->rawPayload());
        $this->assertSame($normalizedEnvelope->classifierContext(), $typedEnvelope->classifierContext());
        $this->assertSame($normalizedEnvelope->protocolFacts(), $typedEnvelope->protocolFacts());
    }

    public function test_event_without_explicit_substrate_contract_does_not_gain_replay_payload_from_public_property(): void
    {
        $typed = $this->makeBackgroundJobEvent();
        $normalized = $typed->normalized();
        $event = new class ($normalized) implements EventInterface {
            public function __construct(
                public readonly NormalizedEvent $normalized,
            ) {}

            public function eventName(): string
            {
                return $this->normalized->eventName();
            }

            public function uniqueId(): ?string
            {
                return $this->normalized->uniqueId();
            }

            public function jobUuid(): ?string
            {
                return $this->normalized->jobUuid();
            }

            public function coreUuid(): ?string
            {
                return $this->normalized->coreUuid();
            }

            public function eventSequence(): ?string
            {
                return $this->normalized->eventSequence();
            }
        };

        $envelope = (new ReplayEnvelopeFactory())->fromEvent($event);

        $this->assertSame('', $envelope->rawPayload());
        $this->assertSame(['event-name' => 'BACKGROUND_JOB'], $envelope->classifierContext());
        $this->assertArrayNotHasKey('content-type', $envelope->protocolFacts());
        $this->assertSame(self::jobUuid(), $envelope->protocolFacts()['job-uuid']);
    }

    private function makeBgapiReply(): BgapiAcceptedReply
    {
        $reply = $this->makeReply(EslFixtureBuilder::bgapiAccepted(self::jobUuid()));

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);

        return $reply;
    }

    private function makeReply(string $fixture): ReplyInterface
    {
        $parser = new FrameParser();
        $parser->feed($fixture);
        $frame = $parser->drain()[0];
        $classified = (new InboundMessageClassifier())->classify($frame);

        return (new ReplyFactory())->fromClassified($classified);
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
