<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Correlation;

use Apntalk\EslCore\Correlation\ObservationSequence;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ObservationSequenceTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Construction
    // ---------------------------------------------------------------------------

    public function test_first_starts_at_position_one(): void
    {
        $seq = ObservationSequence::first();

        $this->assertSame(1, $seq->position());
    }

    public function test_at_creates_explicit_position(): void
    {
        $seq = ObservationSequence::at(42);

        $this->assertSame(42, $seq->position());
    }

    public function test_at_zero_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObservationSequence::at(0);
    }

    public function test_at_negative_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ObservationSequence::at(-1);
    }

    public function test_at_one_is_valid(): void
    {
        $seq = ObservationSequence::at(1);

        $this->assertSame(1, $seq->position());
    }

    // ---------------------------------------------------------------------------
    // Advancing
    // ---------------------------------------------------------------------------

    public function test_next_increments_position_by_one(): void
    {
        $seq  = ObservationSequence::first();
        $next = $seq->next();

        $this->assertSame(2, $next->position());
    }

    public function test_next_returns_new_instance(): void
    {
        $seq  = ObservationSequence::first();
        $next = $seq->next();

        $this->assertNotSame($seq, $next);
    }

    public function test_original_is_unchanged_after_next(): void
    {
        $seq = ObservationSequence::first();
        $seq->next();

        $this->assertSame(1, $seq->position());
    }

    public function test_chained_next_produces_monotonic_sequence(): void
    {
        $seq = ObservationSequence::first();

        $positions = [];
        for ($i = 0; $i < 5; $i++) {
            $positions[] = $seq->position();
            $seq         = $seq->next();
        }

        $this->assertSame([1, 2, 3, 4, 5], $positions);
    }

    // ---------------------------------------------------------------------------
    // Comparison
    // ---------------------------------------------------------------------------

    public function test_is_after_returns_true_when_position_is_greater(): void
    {
        $a = ObservationSequence::at(5);
        $b = ObservationSequence::at(3);

        $this->assertTrue($a->isAfter($b));
    }

    public function test_is_after_returns_false_when_equal(): void
    {
        $a = ObservationSequence::at(5);
        $b = ObservationSequence::at(5);

        $this->assertFalse($a->isAfter($b));
    }

    public function test_is_after_returns_false_when_position_is_less(): void
    {
        $a = ObservationSequence::at(2);
        $b = ObservationSequence::at(5);

        $this->assertFalse($a->isAfter($b));
    }

    public function test_is_before_returns_true_when_position_is_less(): void
    {
        $a = ObservationSequence::at(1);
        $b = ObservationSequence::at(3);

        $this->assertTrue($a->isBefore($b));
    }

    public function test_is_before_returns_false_when_equal(): void
    {
        $a = ObservationSequence::at(3);
        $b = ObservationSequence::at(3);

        $this->assertFalse($a->isBefore($b));
    }

    // ---------------------------------------------------------------------------
    // Equality
    // ---------------------------------------------------------------------------

    public function test_equals_same_position(): void
    {
        $a = ObservationSequence::at(7);
        $b = ObservationSequence::at(7);

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_different_position(): void
    {
        $a = ObservationSequence::at(7);
        $b = ObservationSequence::at(8);

        $this->assertFalse($a->equals($b));
    }
}
