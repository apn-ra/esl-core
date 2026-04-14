<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use Apntalk\EslCore\Contracts\ReplyInterface;

/**
 * A typed ESL reply with its correlation metadata attached.
 *
 * ReplyEnvelope binds a protocol reply to the metadata produced by
 * CorrelationContext at observation time. Together they carry:
 * - the full typed reply (access via reply())
 * - session identity, observation sequence, timestamp
 * - job correlation for BgapiAcceptedReply (Job-UUID → JobCorrelation)
 *
 * This is distinct from ReplayEnvelope (the replay-safe capture substrate).
 * ReplyEnvelope is the correlation layer's view; it carries typed objects
 * and rich metadata but is not designed for serialization or reconstruction.
 *
 * Immutable. Created by upper layers calling CorrelationContext and wrapping
 * the result.
 *
 * @api
 */
final class ReplyEnvelope
{
    public function __construct(
        private readonly ReplyInterface $reply,
        private readonly MessageMetadata $metadata,
    ) {}

    /**
     * The typed ESL reply.
     */
    public function reply(): ReplyInterface
    {
        return $this->reply;
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
     * The connection session that produced this reply.
     */
    public function sessionId(): ?ConnectionSessionId
    {
        return $this->metadata->sessionId();
    }

    /**
     * The monotonically increasing position of this reply within the session.
     */
    public function observationSequence(): ObservationSequence
    {
        return $this->metadata->observationSequence();
    }

    /**
     * Job correlation for BgapiAcceptedReply, null for all other replies.
     */
    public function jobCorrelation(): ?JobCorrelation
    {
        return $this->metadata->jobCorrelation();
    }
}
