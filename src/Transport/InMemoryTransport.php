<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Transport;

use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Exceptions\TransportException;

/**
 * In-memory transport for testing and smoke-path use.
 *
 * Maintains two buffers:
 * - inbound: bytes to be delivered to the read() caller (simulates data from FreeSWITCH)
 * - outbound: bytes written by the protocol layer (captured for assertions)
 *
 * Usage in tests:
 *
 *   $transport = new InMemoryTransport();
 *   $transport->enqueueInbound(EslFixtureBuilder::authRequest());
 *   $data = $transport->read(1024); // returns the auth/request bytes
 *   $transport->write("auth ClueCon\n\n");
 *   $this->assertSame("auth ClueCon\n\n", $transport->drainOutbound());
 *
 * @api
 */
final class InMemoryTransport implements TransportInterface
{
    private string $inboundBuffer  = '';
    private string $outboundBuffer = '';
    private bool $connected        = true;
    private bool $eofOnEmpty       = false;

    public function read(int $maxBytes): ?string
    {
        if (!$this->connected) {
            throw new TransportException('Transport is closed');
        }

        if ($this->inboundBuffer === '') {
            if ($this->eofOnEmpty) {
                return null; // EOF signal
            }
            return ''; // No data available (non-blocking)
        }

        $chunk               = substr($this->inboundBuffer, 0, $maxBytes);
        $this->inboundBuffer = substr($this->inboundBuffer, strlen($chunk));
        return $chunk;
    }

    public function write(string $bytes): void
    {
        if (!$this->connected) {
            throw new TransportException('Transport is closed');
        }

        $this->outboundBuffer .= $bytes;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    // ---------------------------------------------------------------------------
    // Test helpers
    // ---------------------------------------------------------------------------

    /**
     * Queue bytes to be delivered by the next read() call(s).
     */
    public function enqueueInbound(string $bytes): void
    {
        $this->inboundBuffer .= $bytes;
    }

    /**
     * Return and clear all bytes written by write() calls.
     */
    public function drainOutbound(): string
    {
        $data                 = $this->outboundBuffer;
        $this->outboundBuffer = '';
        return $data;
    }

    /**
     * How many bytes are queued for inbound delivery.
     */
    public function pendingInboundBytes(): int
    {
        return strlen($this->inboundBuffer);
    }

    /**
     * If true, read() returns null (EOF) when the inbound buffer is empty.
     * If false (default), read() returns '' (no data available).
     */
    public function setEofOnEmpty(bool $eofOnEmpty): void
    {
        $this->eofOnEmpty = $eofOnEmpty;
    }

    /**
     * Reset to initial state.
     */
    public function reset(): void
    {
        $this->inboundBuffer  = '';
        $this->outboundBuffer = '';
        $this->connected      = true;
        $this->eofOnEmpty     = false;
    }
}
