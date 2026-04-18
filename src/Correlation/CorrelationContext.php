<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;

/**
 * Stateful per-session correlation context.
 *
 * Assigns monotonically increasing ObservationSequence values to each
 * protocol object observed on a connection, and extracts all available
 * correlation metadata from the object's type and content.
 *
 * One CorrelationContext instance should be created per connection session
 * and used for the lifetime of that session.
 *
 * CorrelationContext does NOT:
 * - store a registry of pending jobs
 * - route events to waiters
 * - own any transport or I/O concern
 *
 * It only produces MessageMetadata snapshots. Upper layers decide what
 * to do with that metadata.
 *
 * @api
 */
final class CorrelationContext
{
    private ObservationSequence $sequence;

    public function __construct(
        private readonly ConnectionSessionId $sessionId,
    ) {
        $this->sequence = ObservationSequence::first();
    }

    /**
     * Create a context not tied to any session.
     *
     * Useful for testing or when no session identity is available.
     */
    public static function anonymous(): self
    {
        return new self(ConnectionSessionId::generate());
    }

    /**
     * Build MessageMetadata for a reply, advancing the observation sequence.
     *
     * Extracts:
     * - Job-UUID from BgapiAcceptedReply (JobCorrelation)
     * - ChannelCorrelation is null for replies (replies do not carry channel headers
     *   in the ESL wire protocol; channel context lives in events)
     */
    public function nextMetadataForReply(ReplyInterface $reply): MessageMetadata
    {
        $seq = $this->advance();

        $jobCorrelation = null;
        if ($reply instanceof BgapiAcceptedReply) {
            $jobCorrelation = JobCorrelation::fromBgapiReply($reply);
        }

        return new MessageMetadata(
            sessionId: $this->sessionId,
            observationSequence: $seq,
            observedAtMicros: $this->nowMicros(),
            jobCorrelation: $jobCorrelation,
            channelCorrelation: null,
            protocolSequence: null,
        );
    }

    /**
     * Build MessageMetadata for an event, advancing the observation sequence.
     *
     * Extracts:
     * - ChannelCorrelation from the event (full context from NormalizedEvent,
     *   partial from plain EventInterface when only uniqueId is available)
     * - JobCorrelation from events that carry Job-UUID (e.g., BACKGROUND_JOB)
     * - protocolSequence from the FreeSWITCH Event-Sequence header
     */
    public function nextMetadataForEvent(EventInterface $event): MessageMetadata
    {
        $seq = $this->advance();

        $channelCorrelation = $this->extractChannelCorrelation($event);
        $jobCorrelation     = $this->extractJobCorrelation($event);

        return new MessageMetadata(
            sessionId: $this->sessionId,
            observationSequence: $seq,
            observedAtMicros: $this->nowMicros(),
            jobCorrelation: $jobCorrelation,
            channelCorrelation: $channelCorrelation,
            protocolSequence: $event->eventSequence(),
        );
    }

    /**
     * The session identity for this context.
     */
    public function sessionId(): ConnectionSessionId
    {
        return $this->sessionId;
    }

    /**
     * The current observation sequence (before the next advance).
     */
    public function currentSequence(): ObservationSequence
    {
        return $this->sequence;
    }

    // ---------------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------------

    private function advance(): ObservationSequence
    {
        $current        = $this->sequence;
        $this->sequence = $this->sequence->next();
        return $current;
    }

    private function extractChannelCorrelation(EventInterface $event): ?ChannelCorrelation
    {
        // NormalizedEvent carries full channel context
        if ($event instanceof NormalizedEvent) {
            $correlation = ChannelCorrelation::fromNormalizedEvent($event);
            return $correlation->isEmpty() ? null : $correlation;
        }

        // Prefer the explicit substrate contract when the typed event provides it.
        if ($event instanceof ProvidesNormalizedSubstrateInterface) {
            $correlation = ChannelCorrelation::fromNormalizedEvent($event->normalized());
            return $correlation->isEmpty() ? null : $correlation;
        }

        // Fallback: EventInterface gives us only uniqueId
        $correlation = ChannelCorrelation::fromEvent($event);
        return $correlation->isEmpty() ? null : $correlation;
    }

    private function extractJobCorrelation(EventInterface $event): ?JobCorrelation
    {
        $jobUuid = $event->jobUuid();
        if ($jobUuid === null || $jobUuid === '') {
            return null;
        }
        return JobCorrelation::fromString($jobUuid);
    }

    private function nowMicros(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }
}
