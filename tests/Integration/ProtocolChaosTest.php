<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\ReplayConsistencyException;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use Apntalk\EslCore\Replay\ReplayEnvelope;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replies\UnknownReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Transport\InMemoryTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProtocolChaosTest extends TestCase
{
    private const JOB_UUID = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';

    #[DataProvider('seededFragmentationCases')]
    public function test_seeded_fragmented_noisy_stream_preserves_typed_results_and_metadata(
        int $seed,
        int $maxChunkSize,
    ): void {
        $bgapiAccepted = EslFixtureBuilder::bgapiAccepted(self::JOB_UUID);
        $unknownEvent = EslFixtureBuilder::eventPlain(EslFixtureBuilder::eventData([
            'Event-Name' => 'UNKNOWN_ROUTING_GLITCH',
            'Event-Sequence' => '20001',
            'Unique-ID' => '11111111-1111-4111-8111-111111111111',
            'Channel-Name' => 'sofia/internal/1001@192.168.1.100',
            'Call-Direction' => 'inbound',
        ]));
        $backgroundJob = EslFixtureBuilder::backgroundJobEvent(self::JOB_UUID, "+OK queued\n");

        $stream = $bgapiAccepted . $unknownEvent . $backgroundJob;
        $readSizes = self::seededChunkSizes(strlen($stream), $seed, $maxChunkSize);

        $transport = new InMemoryTransport();
        $transport->enqueueInbound($stream);
        $transport->setEofOnEmpty(true);

        $outboundCommand = "bgapi status\n\n";
        $transport->write($outboundCommand);
        $this->assertSame($outboundCommand, $transport->drainOutbound());

        $results = $this->consumeTransportWithChunkPlan($transport, $readSizes);

        $this->assertCount(3, $results, sprintf('Seed %d should produce three parsed objects', $seed));

        $this->assertSame(InboundMessageCategory::BgapiAccepted, $results[0]['classified']->category);
        $this->assertInstanceOf(BgapiAcceptedReply::class, $results[0]['typed']);
        $this->assertSame(1, $results[0]['observation_sequence']);
        $this->assertSame(1, $results[0]['capture_sequence']);
        $this->assertSame(self::JOB_UUID, $results[0]['protocol_facts']['job-uuid']);
        $this->assertSame(self::JOB_UUID, $results[0]['derived_metadata']['job-correlation.job-uuid']);

        $this->assertSame(InboundMessageCategory::EventMessage, $results[1]['classified']->category);
        $this->assertInstanceOf(RawEvent::class, $results[1]['typed']);
        $this->assertSame('UNKNOWN_ROUTING_GLITCH', $results[1]['typed']->eventName());
        $this->assertSame(2, $results[1]['observation_sequence']);
        $this->assertSame(2, $results[1]['capture_sequence']);
        $this->assertSame('UNKNOWN_ROUTING_GLITCH', $results[1]['protocol_facts']['event-name']);
        $this->assertSame(
            '11111111-1111-4111-8111-111111111111',
            $results[1]['derived_metadata']['channel-correlation.unique-id']
        );

        $this->assertSame(InboundMessageCategory::EventMessage, $results[2]['classified']->category);
        $this->assertInstanceOf(BackgroundJobEvent::class, $results[2]['typed']);
        $this->assertSame(3, $results[2]['observation_sequence']);
        $this->assertSame(3, $results[2]['capture_sequence']);
        $this->assertSame(self::JOB_UUID, $results[2]['protocol_facts']['job-uuid']);
        $this->assertSame(self::JOB_UUID, $results[2]['derived_metadata']['job-correlation.job-uuid']);
        $this->assertSame('12346', $results[2]['protocol_sequence']);

        $expectedSession = '99999999-9999-4999-8999-999999999999';
        $this->assertSame($expectedSession, $results[0]['session_id']);
        $this->assertSame($expectedSession, $results[1]['session_id']);
        $this->assertSame($expectedSession, $results[2]['session_id']);
    }

    public function test_multiple_frames_delivered_in_large_uneven_chunks_still_parse_deterministically(): void
    {
        $stream = EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain')
            . EslFixtureBuilder::apiResponse("+OK status\n");

        $chunkPlan = [
            strlen(EslFixtureBuilder::authAccepted()) + strlen(EslFixtureBuilder::commandReplyOk('+OK event listener enabled plain')),
            3,
            strlen($stream),
        ];

        $transport = new InMemoryTransport();
        $transport->enqueueInbound($stream);
        $transport->setEofOnEmpty(true);

        $results = $this->consumeTransportWithChunkPlan($transport, $chunkPlan);

        $this->assertCount(3, $results);
        $this->assertSame(InboundMessageCategory::AuthAccepted, $results[0]['classified']->category);
        $this->assertSame(InboundMessageCategory::CommandAccepted, $results[1]['classified']->category);
        $this->assertSame(InboundMessageCategory::ApiResponse, $results[2]['classified']->category);
        $this->assertSame([1, 2, 3], array_column($results, 'observation_sequence'));
    }

    public function test_truncated_fragmented_input_fails_with_truncated_frame_exception(): void
    {
        $parser = new FrameParser();
        $fragments = [
            "Content-Type: api/response\nContent-Length: 8\n\n+OK ",
            'sta',
        ];

        foreach ($fragments as $fragment) {
            $parser->feed($fragment);
        }

        $this->expectException(TruncatedFrameException::class);

        $parser->finish();
    }

    public function test_malformed_json_event_fails_explicitly_after_a_valid_reply_frame(): void
    {
        $parser = new FrameParser();
        $replyFactory = new ReplyFactory();
        $classifier = new InboundMessageClassifier();

        $stream = EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::eventJson('{"Event-Name":');

        $parser->feed($stream);
        $frames = $parser->drain();

        $this->assertCount(2, $frames);

        $reply = $replyFactory->fromClassified($classifier->classify($frames[0]));
        $this->assertTrue($reply->isSuccess());

        $this->expectException(MalformedFrameException::class);

        (new EventFactory())->fromFrame($frames[1]);
    }

    public function test_unknown_content_type_degrades_to_unknown_reply_with_coherent_replay_metadata(): void
    {
        $frame = new Frame(
            HeaderBag::fromHeaderBlock("Content-Type: application/x-chaos"),
            '',
        );
        $classifier = new InboundMessageClassifier();
        $replyFactory = new ReplyFactory();
        $context = new CorrelationContext(
            ConnectionSessionId::fromString('aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa')
        );
        $replayFactory = ReplayEnvelopeFactory::withSession($context->sessionId());

        $classified = $classifier->classify($frame);
        $reply = $replyFactory->fromClassified($classified);
        $metadata = $context->nextMetadataForReply($reply);
        $envelope = new ReplyEnvelope($reply, $metadata);
        $replayEnvelope = $replayFactory->fromReplyEnvelope($envelope);

        $this->assertSame(InboundMessageCategory::Unknown, $classified->category);
        $this->assertInstanceOf(UnknownReply::class, $reply);
        $this->assertSame('application/x-chaos', $reply->contentType());
        $this->assertSame('UnknownReply', $replayEnvelope->capturedName());
        $this->assertSame('application/x-chaos', $replayEnvelope->protocolFacts()['content-type']);
        $this->assertSame('1', $replayEnvelope->derivedMetadata()['observation-sequence']);
    }

    public function test_replay_factory_rejects_mismatched_session_after_noisy_sequence(): void
    {
        $reply = $this->parseBgapiReply();
        $context = new CorrelationContext(
            ConnectionSessionId::fromString('11111111-1111-4111-8111-111111111111')
        );
        $mismatchedFactory = ReplayEnvelopeFactory::withSession(
            ConnectionSessionId::fromString('22222222-2222-4222-8222-222222222222')
        );
        $envelope = new ReplyEnvelope($reply, $context->nextMetadataForReply($reply));

        $this->expectException(ReplayConsistencyException::class);

        $mismatchedFactory->fromReplyEnvelope($envelope);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function seededFragmentationCases(): iterable
    {
        yield 'seed-1337-max-5' => [1337, 5];
        yield 'seed-9001-max-11' => [9001, 11];
    }

    /**
     * @param list<int> $chunkPlan
     * @return list<array{
     *   classified: ClassifiedInboundMessage,
     *   typed: object,
     *   session_id: string|null,
     *   observation_sequence: int,
     *   capture_sequence: int,
     *   protocol_sequence: string|null,
     *   protocol_facts: array<string, string>,
     *   derived_metadata: array<string, string>
     * }>
     */
    private function consumeTransportWithChunkPlan(InMemoryTransport $transport, array $chunkPlan): array
    {
        $parser = new FrameParser();
        $classifier = new InboundMessageClassifier();
        $replyFactory = new ReplyFactory();
        $eventFactory = new EventFactory();
        $context = new CorrelationContext(
            ConnectionSessionId::fromString('99999999-9999-4999-8999-999999999999')
        );
        $replayFactory = ReplayEnvelopeFactory::withSession($context->sessionId());
        $results = [];

        foreach ($chunkPlan as $chunkSize) {
            $chunk = $transport->read($chunkSize);
            if ($chunk === null) {
                break;
            }

            if ($chunk === '') {
                continue;
            }

            $parser->feed($chunk);

            foreach ($parser->drain() as $frame) {
                $classified = $classifier->classify($frame);

                if ($classified->category === InboundMessageCategory::EventMessage) {
                    $typed = $eventFactory->fromFrame($frame);
                    $metadata = $context->nextMetadataForEvent($typed);
                    $replayEnvelope = $replayFactory->fromEventEnvelope(
                        new EventEnvelope($typed, $metadata)
                    );
                } else {
                    $typed = $replyFactory->fromClassified($classified);
                    $metadata = $context->nextMetadataForReply($typed);
                    $replayEnvelope = $replayFactory->fromReplyEnvelope(
                        new ReplyEnvelope($typed, $metadata)
                    );
                }

                $results[] = $this->resultRow($classified, $typed, $metadata->sessionId()?->toString(), $metadata->observationSequence()->position(), $replayEnvelope);
            }
        }

        $parser->finish();

        return $results;
    }

    /**
     * @return array{
     *   classified: ClassifiedInboundMessage,
     *   typed: object,
     *   session_id: string|null,
     *   observation_sequence: int,
     *   capture_sequence: int,
     *   protocol_sequence: string|null,
     *   protocol_facts: array<string, string>,
     *   derived_metadata: array<string, string>
     * }
     */
    private function resultRow(
        ClassifiedInboundMessage $classified,
        object $typed,
        ?string $sessionId,
        int $observationSequence,
        ReplayEnvelope $replayEnvelope,
    ): array {
        return [
            'classified' => $classified,
            'typed' => $typed,
            'session_id' => $sessionId,
            'observation_sequence' => $observationSequence,
            'capture_sequence' => $replayEnvelope->captureSequence(),
            'protocol_sequence' => $replayEnvelope->protocolSequence(),
            'protocol_facts' => $replayEnvelope->protocolFacts(),
            'derived_metadata' => $replayEnvelope->derivedMetadata(),
        ];
    }

    /**
     * @return list<int>
     */
    private static function seededChunkSizes(int $length, int $seed, int $maxChunkSize): array
    {
        $remaining = $length;
        $state = $seed;
        $sizes = [];

        while ($remaining > 0) {
            $state = (int) (($state * 1103515245 + 12345) & 0x7fffffff);
            $size = 1 + ($state % min($maxChunkSize, $remaining));
            $sizes[] = $size;
            $remaining -= $size;
        }

        return $sizes;
    }

    private function parseBgapiReply(): BgapiAcceptedReply
    {
        $parser = new FrameParser();
        $parser->feed(EslFixtureBuilder::bgapiAccepted(self::JOB_UUID));
        $frame = $parser->drain()[0];
        $classified = (new InboundMessageClassifier())->classify($frame);
        $reply = (new ReplyFactory())->fromClassified($classified);

        $this->assertInstanceOf(BgapiAcceptedReply::class, $reply);

        return $reply;
    }
}
