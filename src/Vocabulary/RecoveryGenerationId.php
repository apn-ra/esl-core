<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Identity for a recovery/reconnect generation assigned by an upper layer.
 *
 * @api
 */
final class RecoveryGenerationId
{
    public function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Recovery generation ID must be non-empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function fromInteger(int $value): self
    {
        if ($value < 1) {
            throw new InvalidArgumentException('Recovery generation integer must be positive.');
        }

        return new self((string) $value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
