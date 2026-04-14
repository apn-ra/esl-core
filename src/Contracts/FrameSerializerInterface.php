<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for ESL frame serializers.
 *
 * Implementations convert CommandInterface objects to their wire byte
 * representations for transmission to FreeSWITCH.
 */
interface FrameSerializerInterface
{
    /**
     * Serialize a command to ESL wire bytes.
     *
     * The returned string is ready for transmission: it ends with \n\n
     * as required by the ESL protocol.
     */
    public function serialize(CommandInterface $command): string;
}
