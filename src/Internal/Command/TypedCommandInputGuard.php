<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal\Command;

use Apntalk\EslCore\Exceptions\SerializationException;

/**
 * @internal
 */
final class TypedCommandInputGuard
{
    /**
     * @throws SerializationException
     */
    public static function assertNoCrLf(string $value, string $field): void
    {
        if (str_contains($value, "\r") || str_contains($value, "\n")) {
            throw new SerializationException(sprintf(
                'Typed command field "%s" must not contain carriage return or newline characters.',
                $field,
            ));
        }
    }
}
