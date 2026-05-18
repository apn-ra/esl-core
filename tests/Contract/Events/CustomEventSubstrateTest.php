<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Events;

use Apntalk\EslCore\Events\CustomEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class CustomEventSubstrateTest extends TestCase
{
    public function test_custom_event_fixture_decodes_through_public_inbound_pipeline(): void
    {
        $pipeline = InboundPipeline::withDefaults();

        $messages = $pipeline->decode(FixtureLoader::load('events/custom-event-plain.esl'));

        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->isEvent());

        $event = $messages[0]->event();

        $this->assertInstanceOf(CustomEvent::class, $event);
        $this->assertSame('myapp::heartbeat', $event->subclass());
        $this->assertSame('kept-as-is', $event->normalized->header('Hyphenated-Header'));
        $this->assertSame('kept-as-is', $event->normalized->header('hyphenated-header'));
        $this->assertSame('preserve-me', $event->normalized->header('Unknown-Field'));
    }

    public function test_unknown_event_name_falls_back_to_raw_event_with_normalized_substrate(): void
    {
        $pipeline = InboundPipeline::withDefaults();
        $frame = EslFixtureBuilder::eventPlain(EslFixtureBuilder::eventData([
            'Event-Name' => 'SOME_UNKNOWN_EVENT',
            'Hyphenated-Header' => 'kept-as-is',
            'Unknown-Field' => 'preserve-me',
        ]));

        $messages = $pipeline->decode($frame);

        $this->assertCount(1, $messages);
        $this->assertTrue($messages[0]->isEvent());

        $event = $messages[0]->event();
        $normalized = $messages[0]->normalizedEvent();

        $this->assertInstanceOf(RawEvent::class, $event);
        $this->assertNotNull($normalized);
        $this->assertSame('SOME_UNKNOWN_EVENT', $normalized->eventName());
        $this->assertSame('kept-as-is', $normalized->header('Hyphenated-Header'));
        $this->assertSame('kept-as-is', $normalized->header('hyphenated-header'));
        $this->assertSame('preserve-me', $normalized->header('Unknown-Field'));
    }
}
