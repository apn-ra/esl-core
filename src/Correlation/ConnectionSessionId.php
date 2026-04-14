<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use InvalidArgumentException;
use Stringable;

/**
 * Immutable identity for a single ESL connection session.
 *
 * Created once per connection lifecycle. Attached to MessageMetadata and
 * correlation envelopes so that all protocol objects observed on a session
 * can be traced back to the same originating connection.
 *
 * This is NOT a protocol-native identifier — FreeSWITCH does not assign
 * session IDs in the ESL wire protocol. It is assigned by this package
 * (or the upper-layer caller) at connection time.
 *
 * Distinctly separate from:
 * - FreeSWITCH Core-UUID (identifies the FreeSWITCH process, not our connection)
 * - Unique-ID (identifies a channel/call)
 * - Job-UUID (identifies a bgapi job)
 *
 * @api
 */
final class ConnectionSessionId implements Stringable
{
    private function __construct(
        private readonly string $id,
    ) {}

    /**
     * Generate a new, cryptographically random session ID.
     *
     * Produces a UUID v4-formatted string.
     */
    public static function generate(): self
    {
        $bytes = random_bytes(16);

        // Set version to 4
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        // Set variant to RFC 4122
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return new self(sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ));
    }

    /**
     * Restore a session ID from a previously serialized string.
     *
     * @throws InvalidArgumentException if $id is empty.
     */
    public static function fromString(string $id): self
    {
        if ($id === '') {
            throw new InvalidArgumentException('ConnectionSessionId cannot be empty');
        }

        return new self($id);
    }

    public function toString(): string
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
