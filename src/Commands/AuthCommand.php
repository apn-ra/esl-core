<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;
use Apntalk\EslCore\Internal\Command\TypedCommandInputGuard;

/**
 * ESL auth command.
 *
 * Sent by the client after receiving an auth/request frame from FreeSWITCH.
 * Wire format: "auth <password>\n\n"
 */
final class AuthCommand implements CommandInterface
{
    /**
     * @throws SerializationException
     */
    public function __construct(
        private readonly string $password,
    ) {
        TypedCommandInputGuard::assertNoCrLf($this->password, 'password');
    }

    public function password(): string
    {
        return $this->password;
    }

    public function serialize(): string
    {
        return "auth {$this->password}\n\n";
    }
}
