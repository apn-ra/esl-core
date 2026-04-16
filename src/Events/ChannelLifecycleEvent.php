<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;

/**
 * Typed event for channel lifecycle transitions.
 *
 * Covers: CHANNEL_CREATE, CHANNEL_DESTROY, CHANNEL_STATE, CHANNEL_ANSWER,
 * CHANNEL_PROGRESS, CHANNEL_PROGRESS_MEDIA, CHANNEL_OUTGOING.
 *
 * @api
 */
final class ChannelLifecycleEvent implements EventInterface, ProvidesNormalizedSubstrateInterface
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

    public function channelState(): ?string
    {
        return $this->normalized->channelState();
    }

    public function channelName(): ?string
    {
        return $this->normalized->channelName();
    }

    public function callDirection(): ?string
    {
        return $this->normalized->callDirection();
    }

    public function callerIdName(): ?string
    {
        return $this->normalized->callerIdName();
    }

    public function callerIdNumber(): ?string
    {
        return $this->normalized->callerIdNumber();
    }

    public function normalized(): NormalizedEvent
    {
        return $this->normalized;
    }
}
