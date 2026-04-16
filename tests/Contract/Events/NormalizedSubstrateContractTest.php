<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\BridgeEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class NormalizedSubstrateContractTest extends TestCase
{
    private FrameParser $frameParser;
    private EventParser $eventParser;
    private EventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->frameParser = new FrameParser();
        $this->eventParser = new EventParser();
        $this->eventFactory = new EventFactory();
    }

    public function test_normalized_event_implements_explicit_substrate_contract(): void
    {
        $event = $this->normalizedEvent(EslFixtureBuilder::channelCreateEvent());

        $this->assertInstanceOf(ProvidesNormalizedSubstrateInterface::class, $event);
        $this->assertSame($event, $event->normalized());
    }

    public function test_typed_background_job_event_implements_explicit_substrate_contract(): void
    {
        $normalized = $this->normalizedEvent(EslFixtureBuilder::backgroundJobEvent());
        $event = $this->eventFactory->fromNormalized($normalized);

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
        $this->assertInstanceOf(ProvidesNormalizedSubstrateInterface::class, $event);
        $this->assertSame($normalized, $event->normalized());
    }

    public function test_typed_bridge_event_implements_explicit_substrate_contract(): void
    {
        $normalized = $this->normalizedEvent(
            EslFixtureBuilder::eventPlain(
                EslFixtureBuilder::eventData([
                    'Event-Name' => 'CHANNEL_BRIDGE',
                    'Event-Sequence' => '12345',
                    'Unique-ID' => 'bridge-leg-a',
                    'Channel-Name' => 'sofia/internal/1001@192.168.1.100',
                    'Other-Leg-Unique-ID' => 'bridge-leg-b',
                    'Other-Leg-Channel-Name' => 'sofia/internal/1002@192.168.1.100',
                ])
            )
        );
        $event = $this->eventFactory->fromNormalized($normalized);

        $this->assertInstanceOf(BridgeEvent::class, $event);
        $this->assertInstanceOf(ProvidesNormalizedSubstrateInterface::class, $event);
        $this->assertSame($normalized, $event->normalized());
    }

    public function test_raw_event_still_exposes_same_normalized_substrate_through_contract(): void
    {
        $normalized = $this->normalizedEvent(
            EslFixtureBuilder::eventPlain(
                EslFixtureBuilder::eventData([
                    'Event-Name' => 'UNKNOWN_EVENT',
                    'Event-Sequence' => '999',
                    'Unique-ID' => 'unknown-leg',
                ])
            )
        );
        $event = $this->eventFactory->fromNormalized($normalized);

        $this->assertInstanceOf(RawEvent::class, $event);
        $this->assertInstanceOf(ProvidesNormalizedSubstrateInterface::class, $event);
        $this->assertSame($normalized, $event->normalized());
    }

    /**
     * @return EventInterface&ProvidesNormalizedSubstrateInterface
     */
    private function normalizedEvent(string $fixture): NormalizedEvent
    {
        $this->frameParser->reset();
        $this->frameParser->feed($fixture);
        $frames = $this->frameParser->drain();

        $this->assertCount(1, $frames);

        return $this->eventParser->parse($frames[0]);
    }
}
