<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;
use Apntalk\EslCore\Internal\Command\TypedCommandInputGuard;

/**
 * ESL bgapi command — asynchronous background API call.
 *
 * FreeSWITCH immediately replies with a command/reply containing a Job-UUID.
 * The actual command result arrives later as a BACKGROUND_JOB event.
 *
 * Wire format: "bgapi <command>[ <args>]\n\n"
 *
 * The bgapi acceptance reply (Job-UUID) is NOT the command result.
 * The result arrives as a BACKGROUND_JOB event correlated by Job-UUID.
 */
final class BgapiCommand implements CommandInterface
{
    /**
     * @throws SerializationException
     */
    public function __construct(
        private readonly string $command,
        private readonly string $args = '',
    ) {
        TypedCommandInputGuard::assertToken($this->command, 'command');
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
            return "bgapi {$this->command} {$this->args}\n\n";
        }

        return "bgapi {$this->command}\n\n";
    }
}
