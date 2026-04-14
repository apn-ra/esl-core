<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use Apntalk\EslCore\Contracts\EventInterface;

/**
 * A typed ESL event with its correlation metadata attached.
 *
 * EventEnvelope binds a protocol object to the metadata produced by
 * CorrelationContext at observation time. Together they carry:
 * - the full typed event (access via event())
 * - session identity, observation sequence, timestamp
 * - job and channel correlation when available
 *
 * This is distinct from ReplayEnvelope (the replay-safe capture substrate).
 * EventEnvelope is the correlation layer's view; it carries typed objects
 * and rich metadata but is not designed for serialization or reconstruction.
 *
 * Immutable. Created by upper layers calling CorrelationContext and wrapping
 * the result.
 *
 * @api
 */
final class EventEnvelope
{
    public function __construct(
        private readonly EventInterface $event,
        private readonly MessageMetadata $metadata,
    ) {}

    /**
     * The typed ESL event.
     */
    public function event(): EventInterface
    {
        return $this->event;
    }

    /**
     * The correlation metadata assigned at observation time.
     */
    public function metadata(): MessageMetadata
    {
        return $this->metadata;
    }

    // ---------------------------------------------------------------------------
    // Convenience pass-throughs (avoids double indirection at call sites)
    // ---------------------------------------------------------------------------

    /**
     * The connection session that produced this event.
     */
    public function sessionId(): ?ConnectionSessionId
    {
        return $this->metadata->sessionId();
    }

    /**
     * The monotonically increasing position of this event within the session.
     */
    public function observationSequence(): ObservationSequence
    {
        return $this->metadata->observationSequence();
    }

    /**
     * Job correlation, if this event carries a Job-UUID.
     */
    public function jobCorrelation(): ?JobCorrelation
    {
        return $this->metadata->jobCorrelation();
    }

    /**
     * Channel correlation, if this event is associated with a channel.
     */
    public function channelCorrelation(): ?ChannelCorrelation
    {
        return $this->metadata->channelCorrelation();
    }
}
