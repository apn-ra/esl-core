<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal\Transport;

use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Exceptions\TransportException;

/**
 * Minimal stream-socket transport used by bounded smoke/integration paths.
 *
 * This wrapper intentionally owns only byte-oriented read/write/close behavior.
 * It does not add reconnect, scheduling, supervision, or runtime policy.
 *
 * @internal Internal smoke-path transport, not part of the supported public API.
 */
final class StreamSocketTransport implements TransportInterface
{
    /** @var resource|null */
    private $stream;

    /**
     * @param resource $stream Readable/writable PHP stream resource.
     */
    public function __construct($stream)
    {
        if (!is_resource($stream)) {
            throw new TransportException('StreamSocketTransport requires a valid stream resource.');
        }

        $this->stream = $stream;
    }

    public function read(int $maxBytes): ?string
    {
        $stream = $this->requireOpenStream();

        $chunk = fread($stream, $maxBytes);

        if ($chunk === false) {
            throw new TransportException('Failed to read from stream transport.');
        }

        if ($chunk === '' && feof($stream)) {
            return null;
        }

        return $chunk;
    }

    public function write(string $bytes): void
    {
        $stream = $this->requireOpenStream();
        $remaining = $bytes;

        while ($remaining !== '') {
            $written = fwrite($stream, $remaining);

            if ($written === false || $written === 0) {
                throw new TransportException('Failed to write full payload to stream transport.');
            }

            $remaining = substr($remaining, $written);
        }
    }

    public function isConnected(): bool
    {
        return is_resource($this->stream) && !feof($this->stream);
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }

        $this->stream = null;
    }

    /**
     * @return resource
     */
    private function requireOpenStream()
    {
        if (!is_resource($this->stream)) {
            throw new TransportException('Transport is closed');
        }

        return $this->stream;
    }
}
