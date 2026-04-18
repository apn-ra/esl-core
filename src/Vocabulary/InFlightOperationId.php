<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Identity for an operation currently being tracked by an upper layer.
 *
 * @api
 */
final class InFlightOperationId
{
    public function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('In-flight operation ID must be non-empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
