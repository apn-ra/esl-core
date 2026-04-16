<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;

/**
 * Typed event for CHANNEL_BRIDGE and CHANNEL_UNBRIDGE.
 *
 * @api
 */
final class BridgeEvent implements EventInterface, ProvidesNormalizedSubstrateInterface
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

    public function otherLegUniqueId(): ?string
    {
        return $this->normalized->otherLegUniqueId();
    }

    public function otherLegChannelName(): ?string
    {
        return $this->normalized->otherLegChannelName();
    }

    public function normalized(): NormalizedEvent
    {
        return $this->normalized;
    }
}
