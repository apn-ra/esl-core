<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Inbound;

use Apntalk\EslCore\Contracts\ClassifiedMessageInterface;
use Apntalk\EslCore\Contracts\CompletableFrameParserInterface;
use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\ChannelLifecycleEvent;
use Apntalk\EslCore\Events\PlaybackEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use Apntalk\EslCore\Protocol\MessageType;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\ErrorReply;
use Apntalk\EslCore\Replies\UnknownReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class InboundPipelineTest extends TestCase
{
    private InboundPipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = InboundPipeline::withDefaults();
    }

    public function test_decode_auth_request_exposes_stable_notice_without_internal_classifier_types(): void
    {
        $messages = $this->pipeline->decode(EslFixtureBuilder::authRequest());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::ServerAuthRequest, $messages[0]->type());
        $this->assertTrue($messages[0]->isServerAuthRequest());
        $this->assertNull($messages[0]->reply());
        $this->assertNull($messages[0]->event());
    }

    public function test_decode_auth_reply_yields_typed_reply(): void
    {
        $messages = $this->pipeline->decode(EslFixtureBuilder::authAccepted());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(AuthAcceptedReply::class, $messages[0]->reply());
        $this->assertSame('command/reply', $messages[0]->contentType());
    }

    public function test_contract_based_advanced_composition_accepts_public_classifier_result(): void
    {
        $frame = new Frame(
            HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK custom"),
            '',
        );
        $parser = new class ($frame) implements CompletableFrameParserInterface {
            private bool $hasFrame = false;

            public function __construct(
                private readonly Frame $frame,
            ) {}

            public function feed(string $bytes): void
            {
                $this->hasFrame = $bytes !== '';
            }

            public function drain(): array
            {
                if (!$this->hasFrame) {
                    return [];
                }

                $this->hasFrame = false;

                return [$this->frame];
            }

            public function reset(): void
            {
                $this->hasFrame = false;
            }

            public function bufferedByteCount(): int
            {
                return 0;
            }

            public function finish(): void {}
        };
        $classifier = new class implements InboundMessageClassifierInterface {
            public function classify(Frame $frame): ClassifiedMessageInterface
            {
                return new class ($frame) implements ClassifiedMessageInterface {
                    public function __construct(
                        private readonly Frame $frame,
                    ) {}

                    public function frame(): Frame
                    {
                        return $this->frame;
                    }

                    public function isAuthRequest(): bool
                    {
                        return false;
                    }

                    public function isAuthAccepted(): bool
                    {
                        return false;
                    }

                    public function isBgapiAccepted(): bool
                    {
                        return false;
                    }

                    public function isCommandAccepted(): bool
                    {
                        return true;
                    }

                    public function isCommandError(): bool
                    {
                        return false;
                    }

                    public function isApiResponse(): bool
                    {
                        return false;
                    }

                    public function isEvent(): bool
                    {
                        return false;
                    }

                    public function isDisconnectNotice(): bool
                    {
                        return false;
                    }

                    public function isUnknown(): bool
                    {
                        return false;
                    }
                };
            }
        };

        $pipeline = InboundPipeline::withContracts($parser, $classifier);

        $messages = $pipeline->decode("ignored by custom parser\n\n");

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertSame('+OK custom', $messages[0]->reply()?->frame()->replyText());
    }

    public function test_contract_based_pipeline_accepts_classifier_with_older_concrete_return_type(): void
    {
        $frame = new Frame(
            HeaderBag::fromHeaderBlock("Content-Type: command/reply\nReply-Text: +OK old-style"),
            '',
        );
        $parser = new class ($frame) implements CompletableFrameParserInterface {
            private bool $hasFrame = false;

            public function __construct(
                private readonly Frame $frame,
            ) {}

            public function feed(string $bytes): void
            {
                $this->hasFrame = $bytes !== '';
            }

            public function drain(): array
            {
                if (!$this->hasFrame) {
                    return [];
                }

                $this->hasFrame = false;

                return [$this->frame];
            }

            public function reset(): void
            {
                $this->hasFrame = false;
            }

            public function bufferedByteCount(): int
            {
                return 0;
            }

            public function finish(): void {}
        };
        $classifier = new class implements InboundMessageClassifierInterface {
            public function classify(Frame $frame): ClassifiedInboundMessage
            {
                return new ClassifiedInboundMessage(
                    InboundMessageCategory::CommandAccepted,
                    $frame,
                    MessageType::CommandReply,
                );
            }
        };

        $pipeline = InboundPipeline::withContracts($parser, $classifier);

        $messages = $pipeline->decode("ignored by custom parser\n\n");

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertSame('+OK old-style', $messages[0]->reply()?->frame()->replyText());
    }

    public function test_bgapi_acceptance_and_completion_sequence_stays_correlatable_through_public_facade(): void
    {
        $messages = $this->pipeline->decode(FixtureLoader::load('sequences/bgapi-acceptance-and-completion.esl'));

        $this->assertCount(2, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertSame(InboundMessageType::Event, $messages[1]->type());
        $this->assertInstanceOf(BgapiAcceptedReply::class, $messages[0]->reply());
        $this->assertInstanceOf(BackgroundJobEvent::class, $messages[1]->event());
        $this->assertSame(
            $messages[0]->reply()?->frame()->replyText(),
            '+OK Job-UUID: 7f4db0f2-b848-4b0a-b3cf-559bdca96b38'
        );

        /** @var BackgroundJobEvent $event */
        $event = $messages[1]->event();
        $this->assertSame('7f4db0f2-b848-4b0a-b3cf-559bdca96b38', $event->jobUuid());
        $this->assertSame("-ERR no reply yet\n", $event->result());

        $sessionId = ConnectionSessionId::fromString('12121212-1212-4212-8212-121212121212');
        $context = new CorrelationContext($sessionId);
        $replay = ReplayEnvelopeFactory::withSession($sessionId);

        $replyEnvelope = new ReplyEnvelope(
            $messages[0]->reply(),
            $context->nextMetadataForReply($messages[0]->reply())
        );
        $eventEnvelope = new EventEnvelope(
            $event,
            $context->nextMetadataForEvent($event)
        );

        $replyReplay = $replay->fromReplyEnvelope($replyEnvelope);
        $eventReplay = $replay->fromEventEnvelope($eventEnvelope);

        $this->assertSame('7f4db0f2-b848-4b0a-b3cf-559bdca96b38', $replyReplay->protocolFacts()['job-uuid']);
        $this->assertSame('7f4db0f2-b848-4b0a-b3cf-559bdca96b38', $eventReplay->protocolFacts()['job-uuid']);
        $this->assertSame('7f4db0f2-b848-4b0a-b3cf-559bdca96b38', $eventReplay->derivedMetadata()['job-correlation.job-uuid']);
        $this->assertSame('12346', $eventReplay->protocolSequence());
    }

    public function test_unknown_event_name_degrades_to_raw_event_while_preserving_normalized_access(): void
    {
        $messages = $this->pipeline->decode(
            EslFixtureBuilder::eventPlain(
                EslFixtureBuilder::eventData([
                    'Event-Name' => 'UNRECOGNIZED_APPLICATION_EVENT',
                    'Event-Sequence' => '9981',
                    'Unique-ID' => 'abc-123',
                ])
            )
        );

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Event, $messages[0]->type());
        $this->assertInstanceOf(RawEvent::class, $messages[0]->event());
        $this->assertSame('UNRECOGNIZED_APPLICATION_EVENT', $messages[0]->normalizedEvent()?->eventName());
        $this->assertSame('abc-123', $messages[0]->normalizedEvent()?->uniqueId());
    }

    public function test_auth_rejection_arrives_as_error_reply_through_public_facade(): void
    {
        // The classifier cannot distinguish auth -ERR from command -ERR alone;
        // it correctly maps both to CommandError, which the factory resolves to ErrorReply.
        // Upper layers (esl-react) use session state to determine the -ERR context.
        $messages = $this->pipeline->decode(EslFixtureBuilder::authRejected());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(ErrorReply::class, $messages[0]->reply());
        $this->assertFalse($messages[0]->reply()?->isSuccess());
    }

    public function test_disconnect_notice_is_decoded_through_public_facade(): void
    {
        $messages = $this->pipeline->decode(EslFixtureBuilder::disconnectNotice());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::DisconnectNotice, $messages[0]->type());
        $this->assertTrue($messages[0]->isDisconnectNotice());
        $this->assertNull($messages[0]->reply());
        $this->assertNull($messages[0]->event());
    }

    public function test_unknown_content_type_degrades_to_unknown_reply_wrapper(): void
    {
        $messages = $this->pipeline->decode(
            EslFixtureBuilder::frame(['Content-Type' => 'text/surprising-thing'])
        );

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Unknown, $messages[0]->type());
        $this->assertInstanceOf(UnknownReply::class, $messages[0]->reply());
        $this->assertFalse($messages[0]->reply()?->isSuccess());
        $this->assertSame('text/surprising-thing', $messages[0]->contentType());
    }

    public function test_malformed_event_json_still_fails_explicitly_through_public_facade(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->pipeline->decode(
            EslFixtureBuilder::eventJson(
                '{"Event-Name":"PLAYBACK_START","Nested":{"oops":"not scalar"}}'
            )
        );
    }

    public function test_drain_throws_on_mid_batch_decode_failure_without_partial_result_replay(): void
    {
        $this->pipeline->push(
            EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::eventJson('{"Event-Name":"PLAYBACK_START","Nested":{"oops":"not scalar"}}')
            . EslFixtureBuilder::commandReplyOk('+OK after bad event')
        );

        try {
            $this->pipeline->drain();
            $this->fail('Expected malformed mid-batch event payload to fail drain().');
        } catch (MalformedFrameException) {
            $this->assertSame([], $this->pipeline->drain());
        }
    }

    public function test_live_plain_bridge_and_playback_fixtures_decode_with_normalized_substrate_attached(): void
    {
        $bridgeMessages = $this->pipeline->decode(FixtureLoader::loadFrame('live/events/channel-bridge-loopback-plain.esl'));
        $playbackMessages = $this->pipeline->decode(FixtureLoader::loadFrame('live/events/playback-stop-tone-stream-plain.esl'));

        $this->assertCount(1, $bridgeMessages);
        $this->assertCount(1, $playbackMessages);
        $this->assertInstanceOf(BridgeEvent::class, $bridgeMessages[0]->event());
        $this->assertInstanceOf(PlaybackEvent::class, $playbackMessages[0]->event());
        $this->assertSame('CHANNEL_BRIDGE', $bridgeMessages[0]->normalizedEvent()?->eventName());
        $this->assertSame('PLAYBACK_STOP', $playbackMessages[0]->normalizedEvent()?->eventName());
        $this->assertSame('tone_stream://%(250,50,440)', $playbackMessages[0]->normalizedEvent()?->playbackFilePath());
    }

    public function test_typed_event_and_preferred_ingress_facade_share_same_normalized_substrate_instance(): void
    {
        $messages = $this->pipeline->decode(FixtureLoader::loadFrame('live/events/channel-bridge-loopback-plain.esl'));

        $this->assertCount(1, $messages);
        $this->assertInstanceOf(BridgeEvent::class, $messages[0]->event());
        $this->assertInstanceOf(ProvidesNormalizedSubstrateInterface::class, $messages[0]->event());
        $this->assertSame($messages[0]->normalizedEvent(), $messages[0]->event()?->normalized());
    }

    public function test_xml_event_decodes_through_public_facade_without_reaching_internal_parsers(): void
    {
        $messages = $this->pipeline->decode(
            EslFixtureBuilder::eventXml(
                EslFixtureBuilder::eventXmlData([
                    'Event-Name' => 'CHANNEL_CREATE',
                    'Unique-ID' => 'abc-xml-123',
                    'Event-Sequence' => '7001',
                    'Channel-Name' => 'sofia/internal/1001@192.168.1.100',
                ])
            )
        );

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Event, $messages[0]->type());
        $this->assertInstanceOf(ChannelLifecycleEvent::class, $messages[0]->event());
        $this->assertSame('text/event-xml', $messages[0]->normalizedEvent()?->sourceContentType());
        $this->assertFalse($messages[0]->normalizedEvent()?->headersAreUrlEncoded() ?? true);
        $this->assertSame('abc-xml-123', $messages[0]->normalizedEvent()?->uniqueId());
    }

    public function test_malformed_xml_event_still_fails_explicitly_through_public_facade(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->pipeline->decode(
            EslFixtureBuilder::eventXml('<event><headers><Event-Name>CHANNEL_CREATE')
        );
    }

    public function test_finish_fails_when_public_facade_ends_mid_frame(): void
    {
        $frame = EslFixtureBuilder::backgroundJobEvent(jobResult: "+OK delayed body\n");
        $this->pipeline->push(substr($frame, 0, strlen($frame) - 4));

        $this->expectException(TruncatedFrameException::class);

        $this->pipeline->finish();
    }

    public function test_public_facade_rejects_malformed_file_fixture(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->pipeline->decode(FixtureLoader::load('malformed/empty-header-name.esl'));
    }

    public function test_public_facade_rejects_event_with_truncated_inner_body_fixture(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->pipeline->decode(FixtureLoader::load('malformed/event-plain-inner-body-truncated.esl'));
    }

    public function test_public_facade_rejects_event_missing_inner_header_terminator_fixture(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->pipeline->decode(FixtureLoader::load('malformed/event-plain-missing-inner-terminator.esl'));
    }

    public function test_public_facade_reports_truncation_for_partial_file_fixture(): void
    {
        $this->pipeline->push(FixtureLoader::load('partial/api-response-body-truncated-partial.bin'));

        $this->assertSame([], $this->pipeline->drain());

        $this->expectException(TruncatedFrameException::class);

        $this->pipeline->finish();
    }

    public function test_named_default_construction_path_returns_supported_public_facade(): void
    {
        $pipeline = InboundPipeline::withDefaults();
        $messages = $pipeline->decode(EslFixtureBuilder::authAccepted());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(AuthAcceptedReply::class, $messages[0]->reply());
    }

    public function test_direct_constructor_remains_usable_for_advanced_public_composition(): void
    {
        $pipeline = new InboundPipeline();
        $messages = $pipeline->decode(EslFixtureBuilder::authAccepted());

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Reply, $messages[0]->type());
        $this->assertInstanceOf(AuthAcceptedReply::class, $messages[0]->reply());
    }
}
