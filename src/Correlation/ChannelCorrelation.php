<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Events\NormalizedEvent;

/**
 * Correlation metadata for channel-oriented protocol objects.
 *
 * Carries FreeSWITCH channel identifiers observed on an event or reply.
 * All fields are optional because not every protocol object carries complete
 * channel context. Partial correlation is modeled explicitly and honestly.
 *
 * Distinct identifier types carried here:
 * - uniqueId: protocol-native channel UUID (Unique-ID header)
 * - channelName: human-readable channel name (Channel-Name, URL-decoded)
 * - callDirection: inbound/outbound (Call-Direction, URL-decoded)
 *
 * ChannelCorrelation does NOT carry:
 * - Session IDs (those are in ConnectionSessionId)
 * - Job UUIDs (those are in JobCorrelation)
 * - Core UUIDs (FreeSWITCH process identifier, rarely needed for correlation)
 *
 * @api
 */
final class ChannelCorrelation
{
    private function __construct(
        private readonly ?string $uniqueId,
        private readonly ?string $channelName,
        private readonly ?string $callDirection,
    ) {}

    /**
     * Build from a NormalizedEvent, extracting all available channel identifiers.
     */
    public static function fromNormalizedEvent(NormalizedEvent $event): self
    {
        return new self(
            uniqueId:      $event->uniqueId(),
            channelName:   $event->channelName(),
            callDirection: $event->callDirection(),
        );
    }

    /**
     * Build from a generic EventInterface (extracts only uniqueId).
     *
     * Use fromNormalizedEvent() when more context is available.
     */
    public static function fromEvent(EventInterface $event): self
    {
        return new self(
            uniqueId:      $event->uniqueId(),
            channelName:   null,
            callDirection: null,
        );
    }

    /**
     * Build a partial correlation from a Unique-ID alone.
     *
     * Used when the channel UUID is known but other context is unavailable.
     */
    public static function fromUniqueId(string $uniqueId): self
    {
        return new self(
            uniqueId:      $uniqueId,
            channelName:   null,
            callDirection: null,
        );
    }

    /**
     * Build an empty (fully partial) correlation when no channel context is known.
     *
     * Prefer returning null from CorrelationContext instead of using this
     * when there is truly no channel context.
     */
    public static function unknown(): self
    {
        return new self(null, null, null);
    }

    // ---------------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------------

    /**
     * The FreeSWITCH channel UUID (Unique-ID header value), decoded.
     * Null if not present in the protocol object.
     */
    public function uniqueId(): ?string
    {
        return $this->uniqueId;
    }

    /**
     * The channel name (Channel-Name header value), decoded.
     * e.g., "sofia/internal/1001@192.168.1.100"
     */
    public function channelName(): ?string
    {
        return $this->channelName;
    }

    /**
     * The call direction (Call-Direction header value), decoded.
     * e.g., "inbound", "outbound"
     */
    public function callDirection(): ?string
    {
        return $this->callDirection;
    }

    // ---------------------------------------------------------------------------
    // State queries
    // ---------------------------------------------------------------------------

    /**
     * Whether the uniqueId is known (the minimum for meaningful correlation).
     */
    public function hasUniqueId(): bool
    {
        return $this->uniqueId !== null;
    }

    /**
     * Whether all available channel context fields are null.
     */
    public function isEmpty(): bool
    {
        return $this->uniqueId === null
            && $this->channelName === null
            && $this->callDirection === null;
    }

    /**
     * Whether this correlation has some but not all fields populated.
     */
    public function isPartial(): bool
    {
        $present = array_filter([
            $this->uniqueId,
            $this->channelName,
            $this->callDirection,
        ], fn (?string $v) => $v !== null);

        return count($present) > 0 && count($present) < 3;
    }

    /**
     * Whether this correlation has enough context to match on Unique-ID.
     */
    public function canMatch(): bool
    {
        return $this->uniqueId !== null;
    }

    /**
     * Whether this correlation's Unique-ID matches the given UUID string.
     *
     * Returns false if uniqueId is null (partial/empty correlation never matches).
     */
    public function matches(string $uniqueId): bool
    {
        return $this->uniqueId !== null && $this->uniqueId === $uniqueId;
    }

    public function equals(self $other): bool
    {
        return $this->uniqueId === $other->uniqueId
            && $this->channelName === $other->channelName
            && $this->callDirection === $other->callDirection;
    }
}
