<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;

/**
 * Typed event for CUSTOM events.
 *
 * FreeSWITCH modules may send CUSTOM events with an Event-Subclass that
 * identifies the specific custom event type.
 *
 * @api
 */
final class CustomEvent implements EventInterface
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
     * The Event-Subclass identifying the specific custom event type.
     * E.g., "sofia::register", "myapp::event".
     */
    public function subclass(): ?string
    {
        return $this->normalized->eventSubclass();
    }
}
