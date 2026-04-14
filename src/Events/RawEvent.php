<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;

/**
 * Safe degradation event for unknown or unsupported event types.
 *
 * When the EventClassifier encounters an event name it has no typed class for,
 * it wraps the NormalizedEvent in a RawEvent instead of throwing.
 *
 * RawEvent exposes the full NormalizedEvent so consumers can inspect
 * the underlying data without needing to know the specific type.
 *
 * @api
 */
final class RawEvent implements EventInterface
{
    public function __construct(
        public readonly NormalizedEvent $normalized,
    ) {}

    public function eventName(): string
    {
        return $this->normalized->eventName();
    }

    public function uniqueId(): ?string
    {
        return $this->normalized->uniqueId();
    }

    public function jobUuid(): ?string
    {
        return $this->normalized->jobUuid();
    }

    public function coreUuid(): ?string
    {
        return $this->normalized->coreUuid();
    }

    public function eventSequence(): ?string
    {
        return $this->normalized->eventSequence();
    }

    /**
     * Access the full normalized event for raw or custom inspection.
     */
    public function normalized(): NormalizedEvent
    {
        return $this->normalized;
    }
}
