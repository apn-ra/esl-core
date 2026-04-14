<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Inbound;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\PlaybackEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Replies\AuthAcceptedReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Replies\UnknownReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class InboundPipelineTest extends TestCase
{
    private InboundPipeline $pipeline;

    protected function setUp(): void
    {
        $this->pipeline = new InboundPipeline();
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

    public function test_unknown_content_type_degrades_to_unknown_reply_wrapper(): void
    {
        $messages = $this->pipeline->decode(
            EslFixtureBuilder::frame(['Content-Type' => 'text/surprising-thing'])
        );

        $this->assertCount(1, $messages);
        $this->assertSame(InboundMessageType::Unknown, $messages[0]->type());
        $this->assertInstanceOf(UnknownReply::class, $messages[0]->reply());
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
}
