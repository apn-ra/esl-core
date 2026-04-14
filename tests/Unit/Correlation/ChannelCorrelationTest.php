<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Correlation;

use Apntalk\EslCore\Correlation\ChannelCorrelation;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class ChannelCorrelationTest extends TestCase
{
    private const UNIQUE_ID     = 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78';
    private const CHANNEL_NAME  = 'sofia/internal/1001@192.168.1.100';
    private const DIRECTION     = 'inbound';

    // ---------------------------------------------------------------------------
    // fromNormalizedEvent
    // ---------------------------------------------------------------------------

    public function test_from_normalized_event_extracts_all_fields(): void
    {
        $event = $this->parseChannelCreate();
        $corr  = ChannelCorrelation::fromNormalizedEvent($event);

        $this->assertSame(self::UNIQUE_ID, $corr->uniqueId());
        $this->assertSame(self::CHANNEL_NAME, $corr->channelName());
        $this->assertSame(self::DIRECTION, $corr->callDirection());
    }

    public function test_from_normalized_event_full_correlation_is_not_partial(): void
    {
        $event = $this->parseChannelCreate();
        $corr  = ChannelCorrelation::fromNormalizedEvent($event);

        $this->assertFalse($corr->isPartial());
        $this->assertFalse($corr->isEmpty());
    }

    // ---------------------------------------------------------------------------
    // fromUniqueId — partial correlation
    // ---------------------------------------------------------------------------

    public function test_from_unique_id_has_only_unique_id(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertSame(self::UNIQUE_ID, $corr->uniqueId());
        $this->assertNull($corr->channelName());
        $this->assertNull($corr->callDirection());
    }

    public function test_from_unique_id_is_partial(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertTrue($corr->isPartial());
        $this->assertFalse($corr->isEmpty());
    }

    // ---------------------------------------------------------------------------
    // unknown — empty correlation
    // ---------------------------------------------------------------------------

    public function test_unknown_is_empty(): void
    {
        $corr = ChannelCorrelation::unknown();

        $this->assertTrue($corr->isEmpty());
        $this->assertFalse($corr->isPartial());
        $this->assertNull($corr->uniqueId());
        $this->assertNull($corr->channelName());
        $this->assertNull($corr->callDirection());
    }

    public function test_unknown_cannot_match(): void
    {
        $corr = ChannelCorrelation::unknown();

        $this->assertFalse($corr->canMatch());
        $this->assertFalse($corr->matches(self::UNIQUE_ID));
    }

    // ---------------------------------------------------------------------------
    // matches
    // ---------------------------------------------------------------------------

    public function test_matches_returns_true_for_same_unique_id(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertTrue($corr->matches(self::UNIQUE_ID));
    }

    public function test_matches_returns_false_for_different_unique_id(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertFalse($corr->matches('different-uuid'));
    }

    public function test_can_match_is_true_when_unique_id_present(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertTrue($corr->canMatch());
    }

    // ---------------------------------------------------------------------------
    // equals
    // ---------------------------------------------------------------------------

    public function test_equals_same_all_fields(): void
    {
        $a = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);
        $b = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_different_unique_id(): void
    {
        $a = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);
        $b = ChannelCorrelation::fromUniqueId('other-uuid');

        $this->assertFalse($a->equals($b));
    }

    // ---------------------------------------------------------------------------
    // isPartial edge cases
    // ---------------------------------------------------------------------------

    public function test_is_partial_false_when_all_three_present(): void
    {
        $event = $this->parseChannelCreate();
        $corr  = ChannelCorrelation::fromNormalizedEvent($event);

        // All three set — full correlation, not partial
        $this->assertFalse($corr->isPartial());
    }

    public function test_is_partial_true_when_only_one_field_set(): void
    {
        $corr = ChannelCorrelation::fromUniqueId(self::UNIQUE_ID);

        $this->assertTrue($corr->isPartial());
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function parseChannelCreate(): \Apntalk\EslCore\Events\NormalizedEvent
    {
        $parser      = new FrameParser();
        $eventParser = new EventParser();

        $parser->feed(EslFixtureBuilder::channelCreateEvent(self::UNIQUE_ID));
        $frames = $parser->drain();

        return $eventParser->parse($frames[0]);
    }
}
