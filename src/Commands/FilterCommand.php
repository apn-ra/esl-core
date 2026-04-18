<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;
use Apntalk\EslCore\Internal\Command\TypedCommandInputGuard;

/**
 * ESL filter command.
 *
 * Adds or removes an event filter. Only events where the given header
 * matches the given value will be delivered.
 *
 * Wire format: "filter <headerName> <headerValue>\n\n"
 *              "filter delete <headerName> <headerValue>\n\n"
 */
final class FilterCommand implements CommandInterface
{
    /**
     * @throws SerializationException
     */
    public function __construct(
        private readonly string $headerName,
        private readonly string $headerValue,
        private readonly bool $delete = false,
    ) {
        TypedCommandInputGuard::assertToken($this->headerName, 'headerName');
        TypedCommandInputGuard::assertNoCrLf($this->headerValue, 'headerValue');
    }

    /**
     * Create a filter that adds an event filter.
     *
     * @throws SerializationException
     */
    public static function add(string $headerName, string $headerValue): self
    {
        return new self($headerName, $headerValue, false);
    }

    /**
     * Create a filter that removes an existing event filter.
     *
     * @throws SerializationException
     */
    public static function delete(string $headerName, string $headerValue): self
    {
        return new self($headerName, $headerValue, true);
    }

    public function headerName(): string
    {
        return $this->headerName;
    }

    public function headerValue(): string
    {
        return $this->headerValue;
    }

    public function isDelete(): bool
    {
        return $this->delete;
    }

    public function serialize(): string
    {
        if ($this->delete) {
            return "filter delete {$this->headerName} {$this->headerValue}\n\n";
        }

        return "filter {$this->headerName} {$this->headerValue}\n\n";
    }
}
