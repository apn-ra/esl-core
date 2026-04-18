<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Stable ordering identity for publication and projection truth.
 *
 * @api
 */
final class OrderingIdentity
{
    public function __construct(
        private readonly string $source,
        private readonly string $value,
    ) {
        if (trim($source) === '' || trim($value) === '') {
            throw new InvalidArgumentException('Ordering identity source and value must be non-empty.');
        }
    }

    public static function fromSourceAndValue(string $source, string $value): self
    {
        return new self($source, $value);
    }

    public function source(): string
    {
        return $this->source;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * @return array{source: string, value: string}
     */
    public function toArray(): array
    {
        return ['source' => $this->source, 'value' => $this->value];
    }
}
