<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use InvalidArgumentException;

/**
 * Immutable observation sequence number for an inbound protocol object.
 *
 * Represents the order in which protocol objects were observed on a connection
 * within a session. Assigned by CorrelationContext; increases monotonically.
 *
 * This is distinct from:
 * - FreeSWITCH Event-Sequence (assigned by FreeSWITCH, not by this package)
 * - ReplayEnvelope.captureSequence (replay-layer concern)
 *
 * Designed to support: tracing, replay ordering, diagnostics, and audit.
 * Not designed to be a clock, timestamp, or wall-time proxy.
 *
 * Immutable: advancing the sequence always produces a new instance.
 *
 * @api
 */
final class ObservationSequence
{
    private function __construct(
        private readonly int $position,
    ) {}

    /**
     * The starting sequence for a new session (position 1).
     */
    public static function first(): self
    {
        return new self(1);
    }

    /**
     * Create a sequence at an explicit position.
     *
     * Useful for restoring sequences from serialized state.
     *
     * @throws InvalidArgumentException if $position < 1.
     */
    public static function at(int $position): self
    {
        if ($position < 1) {
            throw new InvalidArgumentException(
                "ObservationSequence position must be >= 1, got {$position}"
            );
        }

        return new self($position);
    }

    /**
     * Advance: return the next sequence (this instance is unchanged).
     */
    public function next(): self
    {
        return new self($this->position + 1);
    }

    /**
     * The 1-based position of this sequence.
     */
    public function position(): int
    {
        return $this->position;
    }

    /**
     * Whether this sequence was observed after $other.
     */
    public function isAfter(self $other): bool
    {
        return $this->position > $other->position;
    }

    /**
     * Whether this sequence was observed before $other.
     */
    public function isBefore(self $other): bool
    {
        return $this->position < $other->position;
    }

    public function equals(self $other): bool
    {
        return $this->position === $other->position;
    }
}
