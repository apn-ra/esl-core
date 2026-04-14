<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for reconstruction hooks.
 *
 * Reconstruction hooks receive replay envelopes during a replay pass
 * and can reconstitute application state from the captured protocol data.
 *
 * This contract defines the extension point. The replay execution engine
 * that drives reconstruction lives in upper-layer packages.
 */
interface ReconstructionHookInterface
{
    /**
     * Whether this hook handles the given envelope.
     */
    public function handles(ReplayEnvelopeInterface $envelope): bool;

    /**
     * Apply this hook to a replay envelope.
     *
     * Called during reconstruction pass. Must be idempotent.
     */
    public function apply(ReplayEnvelopeInterface $envelope): void;
}
