<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Identity for a terminal publication fact.
 *
 * @api
 */
final class PublicationId
{
    public function __construct(
        private readonly string $value,
    ) {
        if (trim($value) === '') {
            throw new InvalidArgumentException('Publication ID must be non-empty.');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
