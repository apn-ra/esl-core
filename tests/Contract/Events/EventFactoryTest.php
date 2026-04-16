<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\ChannelLifecycleEvent;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Contract coverage for the lower-level typed event bridge.
 *
 * EventFactory remains public, but this test class intentionally exercises the
 * advanced frame/normalized-event composition path instead of the preferred
 * InboundPipeline ingress facade.
 */
final class EventFactoryTest extends TestCase
{
    private FrameParser $frameParser;
    private EventParser $eventParser;
    private EventFactory $factory;

    protected function setUp(): void
    {
        $this->frameParser = new FrameParser();
        $this->eventParser = new EventParser();
        $this->factory = new EventFactory();
    }

    public function test_from_frame_produces_typed_channel_lifecycle_event(): void
    {
        $event = $this->fromFrame(EslFixtureBuilder::channelCreateEvent());

        $this->assertInstanceOf(ChannelLifecycleEvent::class, $event);
        $this->assertSame('CHANNEL_CREATE', $event->eventName());
    }

    public function test_from_frame_produces_typed_background_job_event(): void
    {
        $event = $this->fromFrame(EslFixtureBuilder::backgroundJobEvent());

        $this->assertInstanceOf(BackgroundJobEvent::class, $event);
        $this->assertSame('BACKGROUND_JOB', $event->eventName());
    }

    public function test_from_normalized_degrades_unknown_event_to_raw_event(): void
    {
        $normalized = $this->normalizedEvent(
            EslFixtureBuilder::eventPlain(
                EslFixtureBuilder::eventData([
                    'Event-Name' => 'UNRECOGNIZED_EDGE_EVENT',
                    'Event-Sequence' => '321',
                ])
            )
        );

        $event = $this->factory->fromNormalized($normalized);

        $this->assertInstanceOf(RawEvent::class, $event);
        $this->assertSame('UNRECOGNIZED_EDGE_EVENT', $event->eventName());
    }

    private function fromFrame(string $fixture): \Apntalk\EslCore\Contracts\EventInterface
    {
        $this->frameParser->reset();
        $this->frameParser->feed($fixture);
        $frames = $this->frameParser->drain();

        $this->assertCount(1, $frames);

        return $this->factory->fromFrame($frames[0]);
    }

    private function normalizedEvent(string $fixture): \Apntalk\EslCore\Events\NormalizedEvent
    {
        $this->frameParser->reset();
        $this->frameParser->feed($fixture);
        $frames = $this->frameParser->drain();

        $this->assertCount(1, $frames);

        return $this->eventParser->parse($frames[0]);
    }
}
