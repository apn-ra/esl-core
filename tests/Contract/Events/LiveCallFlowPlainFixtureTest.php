<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
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
 * Verifies curated live text/event-plain frames promoted from the controlled
 * loopback call-flow smoke run.
 *
 * Source captures:
 * - tools/smoke/captures/20260414T071652Z-call-flow-plain-009-full-frame-509c10ca.esl
 * - tools/smoke/captures/20260414T071652Z-call-flow-plain-011-full-frame-cede70df.esl
 * - tools/smoke/captures/20260414T071652Z-call-flow-plain-016-full-frame-57a2f333.esl
 * - tools/smoke/captures/20260414T071653Z-call-flow-plain-019-full-frame-d12cfd8d.esl
 */
final class LiveCallFlowPlainFixtureTest extends TestCase
{
    private const SESSION_ID = '00000000-0000-4000-8000-000000000001';
    private const CHANNEL_UUID = 'f706acc8-6b57-4f98-9239-84b5ee166043';
    private const PEER_UUID = '8983b442-6b2d-4edf-94a1-9bde3a510d1b';
    private const CORE_UUID = '50cfb839-479a-4c7f-ab0b-5e3d0d4bf6be';

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
     * @return iterable<string, array{fixture: string, eventName: string, typedClass: class-string<EventInterface>, sequence: string}>
     */
    public static function promotedPlainFixtures(): iterable
    {
        yield 'CHANNEL_BRIDGE' => [
            'fixture' => 'live/events/channel-bridge-loopback-plain.esl',
            'eventName' => 'CHANNEL_BRIDGE',
            'typedClass' => BridgeEvent::class,
            'sequence' => '388769',
        ];

        yield 'CHANNEL_UNBRIDGE' => [
            'fixture' => 'live/events/channel-unbridge-loopback-plain.esl',
            'eventName' => 'CHANNEL_UNBRIDGE',
            'typedClass' => BridgeEvent::class,
            'sequence' => '388773',
        ];

        yield 'PLAYBACK_START' => [
            'fixture' => 'live/events/playback-start-tone-stream-plain.esl',
            'eventName' => 'PLAYBACK_START',
            'typedClass' => PlaybackEvent::class,
            'sequence' => '388733',
        ];

        yield 'PLAYBACK_STOP' => [
            'fixture' => 'live/events/playback-stop-tone-stream-plain.esl',
            'eventName' => 'PLAYBACK_STOP',
            'typedClass' => PlaybackEvent::class,
            'sequence' => '388739',
        ];
    }

    /**
     * @param class-string<EventInterface> $typedClass
     */
    #[DataProvider('promotedPlainFixtures')]
    public function test_promoted_plain_fixture_parses_classifies_and_decodes_to_expected_type(
        string $fixture,
        string $eventName,
        string $typedClass,
        string $sequence,
    ): void {
        $frame = $this->loadFrame($fixture);
        $classified = $this->classifier->classify($frame);
        $normalized = $this->eventParser->parse($frame);
        $typed = $this->eventFactory->fromFrame($frame);

        $this->assertSame(InboundMessageCategory::EventMessage, $classified->category);
        $this->assertSame('text/event-plain', $frame->headers->get('Content-Type'));
        $this->assertSame($eventName, $normalized->eventName());
        $this->assertSame($sequence, $normalized->eventSequence());
        $this->assertInstanceOf($typedClass, $typed);
        $this->assertSame($eventName, $typed->eventName());
        $this->assertSame(self::CORE_UUID, $typed->coreUuid());
    }

    public function test_channel_bridge_plain_fixture_preserves_bridge_identifiers(): void
    {
        $event = $this->loadTypedBridge('live/events/channel-bridge-loopback-plain.esl');

        $this->assertSame('CHANNEL_BRIDGE', $event->eventName());
        $this->assertSame('388769', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame(self::PEER_UUID, $event->otherLegUniqueId());
        $this->assertSame('loopback/apn-esl-core-peer-a', $event->otherLegChannelName());
    }

    public function test_channel_unbridge_plain_fixture_preserves_bridge_identifiers(): void
    {
        $event = $this->loadTypedBridge('live/events/channel-unbridge-loopback-plain.esl');

        $this->assertSame('CHANNEL_UNBRIDGE', $event->eventName());
        $this->assertSame('388773', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame(self::PEER_UUID, $event->otherLegUniqueId());
        $this->assertSame('loopback/apn-esl-core-peer-a', $event->otherLegChannelName());
    }

    public function test_playback_start_plain_fixture_decodes_url_encoded_playback_context(): void
    {
        $event = $this->loadNormalized('live/events/playback-start-tone-stream-plain.esl');

        $this->assertSame('PLAYBACK_START', $event->eventName());
        $this->assertSame('388733', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame('tone_stream://%(250,50,440)', $event->playbackFilePath());
        $this->assertSame('tone_stream%3A//%25(250,50,440)', $event->rawHeader('Playback-File-Path'));
    }

    public function test_playback_stop_plain_fixture_decodes_url_encoded_playback_context(): void
    {
        $event = $this->loadNormalized('live/events/playback-stop-tone-stream-plain.esl');

        $this->assertSame('PLAYBACK_STOP', $event->eventName());
        $this->assertSame('388739', $event->eventSequence());
        $this->assertSame(self::CHANNEL_UUID, $event->uniqueId());
        $this->assertSame('tone_stream://%(250,50,440)', $event->playbackFilePath());
        $this->assertSame('tone_stream%3A//%25(250,50,440)', $event->rawHeader('Playback-File-Path'));
        $this->assertSame('done', $event->header('Playback-Status'));
    }

    /**
     * @param class-string<EventInterface> $typedClass
     */
    #[DataProvider('promotedPlainFixtures')]
    public function test_promoted_plain_fixture_correlation_and_replay_metadata_remain_protocol_truthful(
        string $fixture,
        string $eventName,
        string $typedClass,
        string $sequence,
    ): void {
        $event = $this->eventFactory->fromFrame($this->loadFrame($fixture));
        $this->assertInstanceOf($typedClass, $event);

        $sessionId = ConnectionSessionId::fromString(self::SESSION_ID);
        $correlation = new CorrelationContext($sessionId);
        $metadata = $correlation->nextMetadataForEvent($event);
        $envelope = new EventEnvelope($event, $metadata);
        $replay = ReplayEnvelopeFactory::withSession($sessionId)->fromEventEnvelope($envelope);

        $this->assertSame(self::SESSION_ID, $envelope->sessionId()?->toString());
        $this->assertSame(1, $envelope->observationSequence()->position());
        $this->assertSame($sequence, $envelope->metadata()->protocolSequence());
        $this->assertSame(self::CHANNEL_UUID, $envelope->channelCorrelation()?->uniqueId());

        $this->assertSame('event', $replay->capturedType());
        $this->assertSame($eventName, $replay->capturedName());
        $this->assertSame(1, $replay->captureSequence());
        $this->assertSame($sequence, $replay->protocolSequence());
        $this->assertSame('text/event-plain', $replay->protocolFacts()['content-type'] ?? null);
        $this->assertSame($eventName, $replay->protocolFacts()['event-name'] ?? null);
        $this->assertSame(self::CHANNEL_UUID, $replay->derivedMetadata()['channel-correlation.unique-id'] ?? null);
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
}
