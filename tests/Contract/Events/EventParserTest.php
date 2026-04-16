<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\ChannelLifecycleEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\HangupEvent;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Events\PlaybackEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Fixture-backed coverage for the lower-level event parsing path.
 *
 * These tests intentionally exercise advanced/provisional collaborators rather
 * than the preferred byte-ingress facade.
 */
final class EventParserTest extends TestCase
{
    private FrameParser $frameParser;
    private EventParser $eventParser;
    private EventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->frameParser  = new FrameParser();
        $this->eventParser  = new EventParser();
        $this->eventFactory = new EventFactory();
    }

    private function parseEvent(string $fixture): NormalizedEvent
    {
        $this->frameParser->reset();
        $this->frameParser->feed($fixture);
        $frames = $this->frameParser->drain();
        $this->assertCount(1, $frames);
        return $this->eventParser->parse($frames[0]);
    }

    private function typedEvent(string $fixture): \Apntalk\EslCore\Contracts\EventInterface
    {
        $this->frameParser->reset();
        $this->frameParser->feed($fixture);
        $frames = $this->frameParser->drain();
        $this->assertCount(1, $frames);
        return $this->eventFactory->fromFrame($frames[0]);
    }

    // ---------------------------------------------------------------------------
    // CHANNEL_CREATE parsing
    // ---------------------------------------------------------------------------

    public function test_channel_create_event_name_decoded(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertSame('CHANNEL_CREATE', $event->eventName());
    }

    public function test_channel_create_unique_id_decoded(): void
    {
        $uuid  = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78';
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent(uniqueId: $uuid));

        $this->assertSame($uuid, $event->uniqueId());
    }

    public function test_channel_create_core_uuid_decoded(): void
    {
        $coreUuid = '8c0e1d84-c82f-11e6-8842-3bf20b4ac4f6';
        $event    = $this->parseEvent(EslFixtureBuilder::channelCreateEvent(coreUuid: $coreUuid));

        $this->assertSame($coreUuid, $event->coreUuid());
    }

    public function test_channel_create_url_encoded_channel_name_decoded(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        // Channel-Name: sofia/internal/1001%40192.168.1.100 → decoded to sofia/internal/1001@192.168.1.100
        $this->assertSame('sofia/internal/1001@192.168.1.100', $event->channelName());
    }

    public function test_channel_create_url_encoded_caller_id_name_decoded(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        // Caller-Caller-ID-Name: User%201001 → "User 1001"
        $this->assertSame('User 1001', $event->callerIdName());
    }

    public function test_channel_create_has_no_body(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertFalse($event->hasBody());
    }

    public function test_channel_create_event_sequence_present(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertSame('12345', $event->eventSequence());
    }

    // ---------------------------------------------------------------------------
    // BACKGROUND_JOB parsing
    // ---------------------------------------------------------------------------

    public function test_background_job_event_name(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::backgroundJobEvent());

        $this->assertSame('BACKGROUND_JOB', $event->eventName());
    }

    public function test_background_job_uuid_decoded(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        $event   = $this->parseEvent(EslFixtureBuilder::backgroundJobEvent($jobUuid));

        $this->assertSame($jobUuid, $event->jobUuid());
    }

    public function test_background_job_body_contains_result(): void
    {
        $result = "+OK some-uuid\n";
        $event  = $this->parseEvent(EslFixtureBuilder::backgroundJobEvent(jobResult: $result));

        $this->assertTrue($event->hasBody());
        $this->assertSame($result, $event->body());
    }

    public function test_event_json_parses_into_normalized_event(): void
    {
        $fixture = EslFixtureBuilder::eventJson(
            EslFixtureBuilder::eventJsonData([
                'Event-Name' => 'CHANNEL_CREATE',
                'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
                'Event-Sequence' => 12345,
                'Channel-Name' => 'sofia/internal/1001@192.168.1.100',
                'Caller-Caller-ID-Name' => 'User 1001',
            ])
        );

        $event = $this->parseEvent($fixture);

        $this->assertSame('CHANNEL_CREATE', $event->eventName());
        $this->assertSame('sofia/internal/1001@192.168.1.100', $event->channelName());
        $this->assertSame('User 1001', $event->callerIdName());
        $this->assertSame('12345', $event->eventSequence());
    }

    public function test_event_json_with_body_preserves_body(): void
    {
        $fixture = EslFixtureBuilder::eventJson(
            EslFixtureBuilder::eventJsonData(
                [
                    'Event-Name' => 'BACKGROUND_JOB',
                    'Job-UUID' => '7f4db0f2-b848-4b0a-b3cf-559bdca96b38',
                ],
                "+OK json-body\n"
            )
        );

        $event = $this->parseEvent($fixture);

        $this->assertTrue($event->hasBody());
        $this->assertSame("+OK json-body\n", $event->body());
    }

    public function test_event_xml_parses_into_normalized_event(): void
    {
        $fixture = EslFixtureBuilder::eventXml(
            EslFixtureBuilder::eventXmlData([
                'Event-Name' => 'CHANNEL_CREATE',
                'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
                'Event-Sequence' => 12345,
                'Channel-Name' => 'sofia/internal/1001@192.168.1.100',
                'Caller-Caller-ID-Name' => 'User 1001',
            ])
        );

        $event = $this->parseEvent($fixture);

        $this->assertSame('CHANNEL_CREATE', $event->eventName());
        $this->assertSame('sofia/internal/1001@192.168.1.100', $event->channelName());
        $this->assertSame('User 1001', $event->callerIdName());
        $this->assertSame('12345', $event->eventSequence());
    }

    public function test_event_xml_with_body_preserves_body(): void
    {
        $fixture = EslFixtureBuilder::eventXml(
            EslFixtureBuilder::eventXmlData(
                [
                    'Event-Name' => 'BACKGROUND_JOB',
                    'Job-UUID' => '7f4db0f2-b848-4b0a-b3cf-559bdca96b38',
                ],
                "+OK xml-body\n"
            )
        );

        $event = $this->parseEvent($fixture);

        $this->assertTrue($event->hasBody());
        $this->assertSame("+OK xml-body\n", $event->body());
    }

    public function test_plain_event_reports_source_content_type_and_url_encoding_policy(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertSame('text/event-plain', $event->sourceContentType());
        $this->assertTrue($event->headersAreUrlEncoded());
    }

    public function test_json_event_reports_source_content_type_and_non_url_encoded_policy(): void
    {
        $fixture = EslFixtureBuilder::eventJson(
            EslFixtureBuilder::eventJsonData([
                'Event-Name' => 'CHANNEL_CREATE',
                'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            ])
        );

        $event = $this->parseEvent($fixture);

        $this->assertSame('text/event-json', $event->sourceContentType());
        $this->assertFalse($event->headersAreUrlEncoded());
    }

    public function test_xml_event_reports_source_content_type_and_non_url_encoded_policy(): void
    {
        $fixture = EslFixtureBuilder::eventXml(
            EslFixtureBuilder::eventXmlData([
                'Event-Name' => 'CHANNEL_CREATE',
                'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            ])
        );

        $event = $this->parseEvent($fixture);

        $this->assertSame('text/event-xml', $event->sourceContentType());
        $this->assertFalse($event->headersAreUrlEncoded());
    }

    // ---------------------------------------------------------------------------
    // CHANNEL_HANGUP parsing
    // ---------------------------------------------------------------------------

    public function test_hangup_event_name(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::hangupEvent());

        $this->assertSame('CHANNEL_HANGUP', $event->eventName());
    }

    public function test_hangup_cause_decoded(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::hangupEvent(hangupCause: 'NORMAL_CLEARING'));

        $this->assertSame('NORMAL_CLEARING', $event->hangupCause());
    }

    // ---------------------------------------------------------------------------
    // Raw header preservation
    // ---------------------------------------------------------------------------

    public function test_raw_header_returns_url_encoded_value(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        // Raw header should be URL-encoded
        $this->assertSame('sofia/internal/1001%40192.168.1.100', $event->rawHeader('Channel-Name'));
    }

    public function test_decoded_and_raw_differ_for_encoded_values(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $raw     = $event->rawHeader('Channel-Name');
        $decoded = $event->channelName();

        $this->assertNotSame($raw, $decoded);
        $this->assertStringContainsString('%40', $raw ?? '');
        $this->assertStringContainsString('@', $decoded ?? '');
    }

    // ---------------------------------------------------------------------------
    // Generic header() accessor
    // ---------------------------------------------------------------------------

    public function test_header_returns_decoded_value(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertSame('CHANNEL_CREATE', $event->header('Event-Name'));
    }

    public function test_header_returns_null_for_missing_header(): void
    {
        $event = $this->parseEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertNull($event->header('X-Non-Existent'));
    }

    // ---------------------------------------------------------------------------
    // Error handling
    // ---------------------------------------------------------------------------

    public function test_parse_throws_for_non_event_content_type(): void
    {
        $this->expectException(ParseException::class);

        $this->frameParser->feed(EslFixtureBuilder::authRequest());
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    public function test_parse_throws_for_api_response(): void
    {
        $this->expectException(ParseException::class);

        $this->frameParser->feed(EslFixtureBuilder::apiResponse('+OK'));
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    public function test_parse_throws_for_invalid_event_json(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->frameParser->feed(EslFixtureBuilder::eventJson('{"Event-Name":'));
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    public function test_parse_throws_for_event_json_with_nested_header_values(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->frameParser->feed(
            EslFixtureBuilder::eventJson('{"Event-Name":"CHANNEL_CREATE","Nested":{"x":"y"}}')
        );
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    public function test_parse_throws_for_invalid_event_xml(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->frameParser->feed(EslFixtureBuilder::eventXml('<event><headers><Event-Name>CHANNEL_CREATE'));
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    public function test_parse_throws_for_event_xml_with_nested_header_values(): void
    {
        $this->expectException(MalformedFrameException::class);

        $this->frameParser->feed(
            EslFixtureBuilder::eventXml(
                '<event><headers><Event-Name><Nested/></Event-Name></headers></event>'
            )
        );
        $frame = $this->frameParser->drain()[0];
        $this->eventParser->parse($frame);
    }

    // ---------------------------------------------------------------------------
    // Typed event classification via EventFactory
    // ---------------------------------------------------------------------------

    public function test_channel_create_becomes_channel_lifecycle_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertInstanceOf(ChannelLifecycleEvent::class, $event);
    }

    public function test_channel_hangup_becomes_hangup_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::hangupEvent());

        $this->assertInstanceOf(HangupEvent::class, $event);
    }

    public function test_background_job_becomes_background_job_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::backgroundJobEvent());

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
    }

    public function test_event_json_flows_through_event_factory(): void
    {
        $event = $this->typedEvent(
            EslFixtureBuilder::eventJson(
                EslFixtureBuilder::eventJsonData([
                    'Event-Name' => 'CHANNEL_CREATE',
                    'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
                    'Event-Sequence' => 12345,
                ])
            )
        );

        $this->assertInstanceOf(ChannelLifecycleEvent::class, $event);
    }

    public function test_event_xml_flows_through_event_factory(): void
    {
        $event = $this->typedEvent(
            EslFixtureBuilder::eventXml(
                EslFixtureBuilder::eventXmlData([
                    'Event-Name' => 'CHANNEL_CREATE',
                    'Unique-ID' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
                    'Event-Sequence' => 12345,
                ])
            )
        );

        $this->assertInstanceOf(ChannelLifecycleEvent::class, $event);
    }

    public function test_channel_bridge_becomes_bridge_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::bridgeEvent());

        $this->assertInstanceOf(BridgeEvent::class, $event);
    }

    public function test_channel_unbridge_becomes_bridge_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::bridgeEvent('CHANNEL_UNBRIDGE'));

        $this->assertInstanceOf(BridgeEvent::class, $event);
    }

    public function test_playback_start_becomes_playback_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::playbackEvent());

        $this->assertInstanceOf(PlaybackEvent::class, $event);
    }

    public function test_playback_stop_becomes_playback_event(): void
    {
        $event = $this->typedEvent(EslFixtureBuilder::playbackEvent('PLAYBACK_STOP'));

        $this->assertInstanceOf(PlaybackEvent::class, $event);
    }

    public function test_unknown_event_degrades_to_raw_event(): void
    {
        $eventData = EslFixtureBuilder::eventData(['Event-Name' => 'TOTALLY_UNKNOWN_EVENT_XYZ']);
        $fixture   = EslFixtureBuilder::eventPlain($eventData);
        $event     = $this->typedEvent($fixture);

        $this->assertInstanceOf(RawEvent::class, $event);
        $this->assertSame('TOTALLY_UNKNOWN_EVENT_XYZ', $event->eventName());
    }

    // ---------------------------------------------------------------------------
    // BackgroundJobEvent specific accessors
    // ---------------------------------------------------------------------------

    public function test_background_job_event_job_uuid(): void
    {
        $jobUuid = '7f4db0f2-b848-4b0a-b3cf-559bdca96b38';
        /** @var BackgroundJobEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::backgroundJobEvent($jobUuid));

        $this->assertSame($jobUuid, $event->jobUuid());
    }

    public function test_background_job_event_result(): void
    {
        $result = "+OK some-uuid\n";
        /** @var BackgroundJobEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::backgroundJobEvent(jobResult: $result));

        $this->assertSame($result, $event->result());
        $this->assertTrue($event->isSuccess());
    }

    public function test_background_job_event_job_command(): void
    {
        /** @var BackgroundJobEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::backgroundJobEvent());

        $this->assertSame('status', $event->jobCommand());
    }

    // ---------------------------------------------------------------------------
    // HangupEvent specific accessors
    // ---------------------------------------------------------------------------

    public function test_hangup_event_typed_hangup_cause(): void
    {
        /** @var HangupEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::hangupEvent(hangupCause: 'NORMAL_CLEARING'));

        $this->assertSame('NORMAL_CLEARING', $event->hangupCause());
    }

    public function test_bridge_event_exposes_other_leg_correlation_fields(): void
    {
        /** @var BridgeEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::bridgeEvent());

        $this->assertSame('b4fbde13-9c33-45d7-a1e4-e6517eb8de91', $event->otherLegUniqueId());
        $this->assertSame('sofia/internal/1002@192.168.1.100', $event->otherLegChannelName());
    }

    public function test_playback_event_exposes_protocol_native_playback_fields(): void
    {
        /** @var PlaybackEvent $event */
        $event = $this->typedEvent(EslFixtureBuilder::playbackEvent());

        $this->assertSame('c8dc43f6-5aa9-46b0-8ef2-14610d46a4d0', $event->playbackUuid());
        $this->assertSame('/tmp/demo.wav', $event->playbackFilePath());
    }

    // ---------------------------------------------------------------------------
    // RawEvent preserves normalized event
    // ---------------------------------------------------------------------------

    public function test_raw_event_preserves_normalized(): void
    {
        $eventData = EslFixtureBuilder::eventData(['Event-Name' => 'UNKNOWN_EVENT']);
        $fixture   = EslFixtureBuilder::eventPlain($eventData);

        /** @var RawEvent $event */
        $event = $this->typedEvent($fixture);

        $this->assertInstanceOf(NormalizedEvent::class, $event->normalized());
        $this->assertSame('UNKNOWN_EVENT', $event->normalized()->eventName());
    }
}
