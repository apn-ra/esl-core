<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for replay capture sinks.
 *
 * A capture sink receives replay envelopes for durable storage.
 * The actual storage backend (memory, database, file, queue) is
 * the responsibility of the implementation, which lives in upper-layer
 * packages, not in esl-core.
 */
interface ReplayCaptureSinkInterface
{
    /**
     * Capture a replay envelope.
     *
     * Implementations should be non-throwing. If capture fails,
     * they should log and continue rather than propagating exceptions
     * into the protocol path.
     */
    public function capture(ReplayEnvelopeInterface $envelope): void;
}
