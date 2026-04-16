<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;

/**
 * Typed event for PLAYBACK_START and PLAYBACK_STOP.
 *
 * @api
 */
final class PlaybackEvent implements EventInterface, ProvidesNormalizedSubstrateInterface
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

    public function channelName(): ?string
    {
        return $this->normalized->channelName();
    }

    public function playbackUuid(): ?string
    {
        return $this->normalized->playbackUuid();
    }

    public function playbackFilePath(): ?string
    {
        return $this->normalized->playbackFilePath();
    }

    public function normalized(): NormalizedEvent
    {
        return $this->normalized;
    }
}
