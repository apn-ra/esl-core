<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;

/**
 * ESL exit command.
 *
 * Requests a graceful disconnection from FreeSWITCH.
 * FreeSWITCH will send a text/disconnect-notice and close the connection.
 *
 * Wire format: "exit\n\n"
 */
final class ExitCommand implements CommandInterface
{
    public function serialize(): string
    {
        return "exit\n\n";
    }
}
