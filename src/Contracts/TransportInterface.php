<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Exceptions\TransportException;

/**
 * Minimal transport contract for ESL connections.
 *
 * Implementations are responsible for raw byte I/O only.
 * They must not own event loops, reconnection logic, or supervision.
 *
 * This interface is intentionally minimal. Upper-layer packages
 * (esl-react, laravel-freeswitch-esl) own the actual transport
 * lifecycle and loop integration.
 */
interface TransportInterface
{
    /**
     * Read up to $maxBytes bytes from the transport.
     *
     * Returns an empty string if no data is available (non-blocking mode).
     * Returns null to signal EOF / connection closed.
     *
     * @throws TransportException on I/O error.
     */
    public function read(int $maxBytes): ?string;

    /**
     * Write bytes to the transport.
     *
     * Stream-backed implementations in this release line assume the underlying
     * stream is currently writable, usually by using blocking streams or by
     * letting the embedding runtime wait for write readiness first. Core does
     * not define async would-block retry, buffering, or scheduling semantics.
     *
     * @throws TransportException on I/O error.
     */
    public function write(string $bytes): void;

    /**
     * Whether the transport is currently open and usable.
     */
    public function isConnected(): bool;

    /**
     * Close the transport.
     */
    public function close(): void;
}
