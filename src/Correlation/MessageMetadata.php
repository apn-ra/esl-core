<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

/**
 * Metadata attached to a single inbound protocol object.
 *
 * Carries all correlation and observability context for one reply or event:
 * - session identity (which connection produced this object)
 * - observation ordering (deterministic position within the session)
 * - observation timestamp (microseconds, wall-clock at receipt)
 * - job correlation (when applicable — bgapi flows)
 * - channel correlation (when applicable — channel events and replies)
 * - protocol sequence (FreeSWITCH Event-Sequence, when present)
 *
 * MessageMetadata is NOT a FreeSWITCH protocol type — it is assigned by
 * CorrelationContext and attached to envelopes by upper-layer callers.
 *
 * Immutable. All fields are readable. No defaults are silently substituted.
 *
 * @api
 */
final class MessageMetadata
{
    public function __construct(
        private readonly ?ConnectionSessionId $sessionId,
        private readonly ObservationSequence  $observationSequence,
        private readonly int                  $observedAtMicros,
        private readonly ?JobCorrelation      $jobCorrelation,
        private readonly ?ChannelCorrelation  $channelCorrelation,
        private readonly ?string              $protocolSequence,
    ) {}

    // ---------------------------------------------------------------------------
    // Accessors
    // ---------------------------------------------------------------------------

    /**
     * The connection session that produced this protocol object.
     * Null if no session was associated at observation time.
     */
    public function sessionId(): ?ConnectionSessionId
    {
        return $this->sessionId;
    }

    /**
     * The monotonically increasing position of this object within the session.
     */
    public function observationSequence(): ObservationSequence
    {
        return $this->observationSequence;
    }

    /**
     * Microsecond wall-clock timestamp at the time this object was observed.
     */
    public function observedAtMicros(): int
    {
        return $this->observedAtMicros;
    }

    /**
     * Job correlation for bgapi flows, if this object carries a Job-UUID.
     * Null for all other protocol objects.
     */
    public function jobCorrelation(): ?JobCorrelation
    {
        return $this->jobCorrelation;
    }

    /**
     * Channel correlation, if this object is associated with a channel.
     * Null when no channel context is available.
     */
    public function channelCorrelation(): ?ChannelCorrelation
    {
        return $this->channelCorrelation;
    }

    /**
     * The FreeSWITCH protocol-native Event-Sequence value, if present.
     * This is distinct from ObservationSequence (which is assigned by this package).
     * Null for reply objects, which carry no Event-Sequence header.
     */
    public function protocolSequence(): ?string
    {
        return $this->protocolSequence;
    }

    // ---------------------------------------------------------------------------
    // State queries
    // ---------------------------------------------------------------------------

    /**
     * Whether this metadata is associated with a known session.
     */
    public function hasSession(): bool
    {
        return $this->sessionId !== null;
    }

    /**
     * Whether this metadata carries bgapi job correlation.
     */
    public function hasJobCorrelation(): bool
    {
        return $this->jobCorrelation !== null;
    }

    /**
     * Whether this metadata carries channel correlation.
     */
    public function hasChannelCorrelation(): bool
    {
        return $this->channelCorrelation !== null;
    }
}
