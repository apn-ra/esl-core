<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;
use Apntalk\EslCore\Internal\Command\TypedCommandInputGuard;

/**
 * ESL api command — synchronous API call.
 *
 * FreeSWITCH executes the command and replies with an api/response frame
 * containing the command output.
 *
 * Wire format: "api <command>[ <args>]\n\n"
 */
final class ApiCommand implements CommandInterface
{
    /**
     * @throws SerializationException
     */
    public function __construct(
        private readonly string $command,
        private readonly string $args = '',
    ) {
        TypedCommandInputGuard::assertNoCrLf($this->command, 'command');
        TypedCommandInputGuard::assertNoCrLf($this->args, 'args');
    }

    public function command(): string
    {
        return $this->command;
    }

    public function args(): string
    {
        return $this->args;
    }

    public function serialize(): string
    {
        if ($this->args !== '') {
            return "api {$this->command} {$this->args}\n\n";
        }

        return "api {$this->command}\n\n";
    }
}
