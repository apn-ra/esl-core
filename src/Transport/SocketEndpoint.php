<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Transport;

use InvalidArgumentException;

/**
 * Stable public connection input for minimal socket-based transport creation.
 *
 * This value object intentionally captures only endpoint-level construction
 * inputs. Runtime behavior such as reconnect policy, heartbeats, and scheduler
 * ownership stays outside core.
 *
 * @api
 */
final class SocketEndpoint
{
    /**
     * @param array<string, mixed> $contextOptions
     */
    public function __construct(
        private string $address,
        private float $timeoutSeconds = 30.0,
        private array $contextOptions = [],
        private int $flags = STREAM_CLIENT_CONNECT,
    ) {
        if ($address === '') {
            throw new InvalidArgumentException('SocketEndpoint address must not be empty.');
        }

        if ($timeoutSeconds <= 0) {
            throw new InvalidArgumentException('SocketEndpoint timeout must be greater than zero.');
        }
    }

    /**
     * @param array<string, mixed> $contextOptions
     */
    public static function tcp(
        string $host,
        int $port,
        float $timeoutSeconds = 30.0,
        array $contextOptions = [],
        int $flags = STREAM_CLIENT_CONNECT,
    ): self {
        if ($host === '') {
            throw new InvalidArgumentException('SocketEndpoint host must not be empty.');
        }

        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('SocketEndpoint port must be between 1 and 65535.');
        }

        return new self(
            sprintf('tcp://%s:%d', $host, $port),
            $timeoutSeconds,
            $contextOptions,
            $flags,
        );
    }

    public function address(): string
    {
        return $this->address;
    }

    public function timeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    /**
     * @return array<string, mixed>
     */
    public function contextOptions(): array
    {
        return $this->contextOptions;
    }

    public function flags(): int
    {
        return $this->flags;
    }
}
