<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;

/**
 * ESL noevents command.
 *
 * Cancels all event subscriptions. No events will be delivered until
 * a new EventSubscriptionCommand is sent.
 *
 * Wire format: "noevents\n\n"
 */
final class NoEventsCommand implements CommandInterface
{
    public function serialize(): string
    {
        return "noevents\n\n";
    }
}
