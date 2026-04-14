<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Serialization;

use Apntalk\EslCore\Contracts\CommandInterface;
use Apntalk\EslCore\Contracts\FrameSerializerInterface;

/**
 * Serializes CommandInterface objects to ESL wire bytes.
 *
 * This is a thin adapter — serialization logic lives in each command's
 * serialize() method. This class exists to satisfy the FrameSerializerInterface
 * contract for consumers that depend on the abstraction.
 */
final class CommandSerializer implements FrameSerializerInterface
{
    public function serialize(CommandInterface $command): string
    {
        return $command->serialize();
    }
}
