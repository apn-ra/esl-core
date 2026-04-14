<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;

/**
 * Raw ESL command — an escape hatch for arbitrary command strings.
 *
 * Use this only when no typed command covers the required use case.
 * The raw string MUST end with \n\n. If it does not, a SerializationException
 * is thrown at construction time.
 *
 * The typed command classes (ApiCommand, BgapiCommand, etc.) are the preferred
 * way to send commands. RawCommand exists for protocol-level testing and for
 * ESL commands not yet covered by typed classes.
 */
final class RawCommand implements CommandInterface
{
    private readonly string $raw;

    /**
     * @throws SerializationException if the raw string does not end with \n\n.
     */
    public function __construct(string $raw)
    {
        if (!str_ends_with($raw, "\n\n")) {
            throw new SerializationException(
                "RawCommand must end with \\n\\n. Got: " . json_encode(substr($raw, -4))
            );
        }

        $this->raw = $raw;
    }

    public function serialize(): string
    {
        return $this->raw;
    }
}
