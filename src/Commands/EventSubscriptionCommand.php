<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Commands;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Exceptions\SerializationException;
use Apntalk\EslCore\Internal\Command\TypedCommandInputGuard;

/**
 * ESL event subscription command.
 *
 * Subscribes to one or more event types. An empty event name list means
 * subscribe to all events (equivalent to "event plain all").
 *
 * Wire format: "event <format> [<event-name> ...]\n\n"
 *              "event <format> all\n\n"
 *
 * Examples:
 *   EventSubscriptionCommand::all()
 *   EventSubscriptionCommand::forNames(['CHANNEL_CREATE', 'CHANNEL_HANGUP'])
 */
final class EventSubscriptionCommand implements CommandInterface
{
    /**
     * @param list<string> $eventNames Empty means subscribe to all events.
     *
     * @throws SerializationException
     */
    public function __construct(
        private readonly EventFormat $format,
        private readonly array $eventNames = [],
    ) {
        foreach ($this->eventNames as $index => $eventName) {
            TypedCommandInputGuard::assertToken($eventName, sprintf('eventNames[%d]', $index));
        }
    }

    /**
     * Subscribe to all events in plain text format.
     *
     * @throws SerializationException
     */
    public static function all(EventFormat $format = EventFormat::Plain): self
    {
        return new self($format, []);
    }

    /**
     * Subscribe to specific named events.
     *
     * @param list<string> $names
     *
     * @throws SerializationException
     */
    public static function forNames(array $names, EventFormat $format = EventFormat::Plain): self
    {
        return new self($format, $names);
    }

    public function format(): EventFormat
    {
        return $this->format;
    }

    /**
     * @return list<string>
     */
    public function eventNames(): array
    {
        return $this->eventNames;
    }

    public function isAllEvents(): bool
    {
        return empty($this->eventNames);
    }

    public function serialize(): string
    {
        $fmt = $this->format->value;

        if (empty($this->eventNames)) {
            return "event {$fmt} all\n\n";
        }

        $names = implode(' ', $this->eventNames);
        return "event {$fmt} {$names}\n\n";
    }
}
