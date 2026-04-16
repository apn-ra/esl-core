<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;

/**
 * Typed event for BACKGROUND_JOB completions.
 *
 * FreeSWITCH sends this event when a bgapi command completes. The event
 * body contains the command output, and the Job-UUID correlates to the
 * earlier BgapiAcceptedReply.
 *
 * @api
 */
final class BackgroundJobEvent implements EventInterface, ProvidesNormalizedSubstrateInterface
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

    public function coreUuid(): ?string
    {
        return $this->normalized->coreUuid();
    }

    public function eventSequence(): ?string
    {
        return $this->normalized->eventSequence();
    }

    /**
     * The Job-UUID that correlates this result to the originating BgapiCommand.
     */
    public function jobUuid(): ?string
    {
        return $this->normalized->jobUuid();
    }

    /**
     * The bgapi command that produced this result.
     */
    public function jobCommand(): ?string
    {
        return $this->normalized->jobCommand();
    }

    /**
     * The command result (the event body).
     */
    public function result(): string
    {
        return $this->normalized->body();
    }

    /**
     * Whether the result starts with "+OK".
     */
    public function isSuccess(): bool
    {
        return str_starts_with(ltrim($this->result()), '+OK');
    }

    public function normalized(): NormalizedEvent
    {
        return $this->normalized;
    }
}
