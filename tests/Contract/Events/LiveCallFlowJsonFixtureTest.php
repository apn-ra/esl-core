<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Events\PlaybackEvent;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies curated live text/event-json frames promoted from the controlled
 * loopback call-flow smoke run.
 *
 * This class intentionally uses lower-level parser/classifier/event-factory
 * assembly to harden fixture truth, not to redefine the preferred downstream
 * ingress path, which remains InboundPipeline.
 *
 * Source captures:
 * - tools/smoke/captures/20260414T070855Z-call-flow-json-009-full-frame-8cd0c2e0.esl
 * - tools/smoke/captures/20260414T070855Z-call-flow-json-010-full-frame-5c316866.esl
 * - tools/smoke/captures/20260414T070855Z-call-flow-json-011-full-frame-a4086769.esl
 * - tools/smoke/captures/20260414T070855Z-call-flow-json-016-full-frame-7da7a950.esl
 * - tools/smoke/captures/20260414T070856Z-call-flow-json-019-full-frame-a2704145.esl
 */
final class LiveCallFlowJsonFixtureTest extends TestCase
{
    private const SESSION_ID = '99999999-9999-4999-8999-999999999999';
    private const CHANNEL_UUID = '03938b92-baa4-49b6-bab2-63b599403395';
    private const PEER_UUID = 'cbb0a495-37ec-49b7-9198-35f5d1fe2505';
    private const CORE_UUID = '50cfb839-479a-4c7f-ab0b-5e3d0d4bf6be';
    private const ORIGINATING_UUID = 'bbc848fb-ad50-4908-9ae4-6b124ca4d3b1';
    private const JOB_UUID = '1dd29eca-8fed-476a-a9e8-69dc5615f2e2';

    private FrameParser $frameParser;
    private InboundMessageClassifier $classifier;
    private EventParser $eventParser;
    private EventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->frameParser = new FrameParser();
        $this->classifier = new InboundMessageClassifier();
        $this->eventParser = new EventParser();
        $this->eventFactory = new EventFactory();
    }

    /**
     * @return iterable<string, array{fixture: string, eventName: string, typedClass: class-string<EventInterface>}>
     */
    public static function promotedJsonFixtures(): iterable
    {
        yield 'CHANNEL_BRIDGE' => [
            'fixture' => 'live/events/channel-bridge-loopback-json.esl',
            'eventName' => 'CHANNEL_BRIDGE',
            'typedClass' => BridgeEvent::class,
        ];

        yield 'CHANNEL_UNBRIDGE' => [
            'fixture' => 'live/events/channel-unbridge-loopback-json.esl',
            'eventName' => 'CHANNEL_UNBRIDGE',
            'typedClass' => BridgeEvent::class,
        ];

        yield 'PLAYBACK_START' => [
            'fixture' => 'live/events/playback-start-tone-stream-json.esl',
            'eventName' => 'PLAYBACK_START',
            'typedClass' => PlaybackEvent::class,
        ];

        yield 'PLAYBACK_STOP' => [
            'fixture' => 'live/events/playback-stop-tone-stream-json.esl',
            'eventName' => 'PLAYBACK_STOP',
            'typedClass' => PlaybackEvent::class,
        ];

        yield 'BACKGROUND_JOB' => [
            'fixture' => 'live/events/background-job-originate-ok-json.esl',
            'eventName' => 'BACKGROUND_JOB',
            'typedClass' => BackgroundJobEvent::class,
        ];
    }

    /**
     * @param class-string<EventInterface> $typedClass
     */
    #[DataProvider('promotedJsonFixtures')]
    public function test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type(
        string $fixture,
        string $eventName,
        string $typedClass,
    ): void {
        $frame = $this->loadFrame($fixture);
        $classified = $this->classifier->classify($frame);
        $normalized = $this->eventParser->parse($frame);
        $typed = $this->eventFactory->fromFrame($frame);

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
        $this->assertSame('text/event-json', $frame->headers->get('Content-Type'));
        $this->assertSame($eventName, $normalized->eventName());
        $this->assertInstanceOf($typedClass, $typed);
        $this->assertSame($eventName, $typed->eventName());
        $this->assertSame(self::CORE_UUID, $typed->coreUuid());
    }

    public function test_channel_bridge_json_fixture_preserves_bridge_identifiers(): void
    {
        $event = $this->loadTypedBridge('live/events/channel-bridge-loopback-json.esl');

        $this->assertSame('CHANNEL_BRIDGE', $event->eventName());
        $this->assertSame('388611', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame(self::PEER_UUID, $event->otherLegUniqueId());
        $this->assertSame('loopback/apn-esl-core-peer-a', $event->otherLegChannelName());
    }

    public function test_channel_unbridge_json_fixture_preserves_bridge_identifiers(): void
    {
        $event = $this->loadTypedBridge('live/events/channel-unbridge-loopback-json.esl');

        $this->assertSame('CHANNEL_UNBRIDGE', $event->eventName());
        $this->assertSame('388615', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame(self::PEER_UUID, $event->otherLegUniqueId());
        $this->assertSame('loopback/apn-esl-core-peer-a', $event->otherLegChannelName());
    }

    public function test_playback_start_json_fixture_preserves_playback_context(): void
    {
        $event = $this->loadNormalized('live/events/playback-start-tone-stream-json.esl');

        $this->assertSame('PLAYBACK_START', $event->eventName());
        $this->assertSame('388575', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame('tone_stream://%(250,50,440)', $event->playbackFilePath());
    }

    public function test_playback_stop_json_fixture_preserves_playback_context(): void
    {
        $event = $this->loadNormalized('live/events/playback-stop-tone-stream-json.esl');

        $this->assertSame('PLAYBACK_STOP', $event->eventName());
        $this->assertSame('388581', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame('tone_stream://%(250,50,440)', $event->playbackFilePath());
        $this->assertSame('done', $event->header('Playback-Status'));
    }

    public function test_background_job_json_fixture_preserves_success_body_and_job_correlation(): void
    {
        $event = $this->loadTypedBackgroundJob('live/events/background-job-originate-ok-json.esl');

        $this->assertTrue($event->isSuccess());
        $this->assertSame(self::JOB_UUID, $event->jobUuid());
        $this->assertSame('originate', $event->jobCommand());
        $this->assertSame("+OK " . self::ORIGINATING_UUID . "\n", $event->result());
    }

    public function test_background_job_json_fixture_correlation_and_replay_metadata_remain_protocol_truthful(): void
    {
        $event = $this->loadTypedBackgroundJob('live/events/background-job-originate-ok-json.esl');
        $sessionId = ConnectionSessionId::fromString(self::SESSION_ID);
        $correlation = new CorrelationContext($sessionId);
        $metadata = $correlation->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replay = ReplayEnvelopeFactory::withSession($sessionId)->fromEventEnvelope($envelope);

        $this->assertSame(self::JOB_UUID, $envelope->jobCorrelation()?->jobUuid());
        $this->assertSame('388577', $envelope->metadata()->protocolSequence());
        $this->assertSame(self::JOB_UUID, $replay->protocolFacts()['job-uuid'] ?? null);
        $this->assertSame(self::JOB_UUID, $replay->derivedMetadata()['job-correlation.job-uuid'] ?? null);
        $this->assertSame('388577', $replay->protocolSequence());
    }

    private function loadFrame(string $fixture): Frame
    {
        $this->frameParser->reset();
        $this->frameParser->feed(FixtureLoader::loadFrame($fixture));
        $frames = $this->frameParser->drain();

        $this->assertCount(1, $frames);

        return $frames[0];
    }

    private function loadNormalized(string $fixture): NormalizedEvent
    {
        return $this->eventParser->parse($this->loadFrame($fixture));
    }

    private function loadTypedBridge(string $fixture): BridgeEvent
    {
        $event = $this->eventFactory->fromFrame($this->loadFrame($fixture));

        $this->assertInstanceOf(BridgeEvent::class, $event);

        return $event;
    }

    private function loadTypedBackgroundJob(string $fixture): BackgroundJobEvent
    {
        $event = $this->eventFactory->fromFrame($this->loadFrame($fixture));

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);

        return $event;
    }
}
