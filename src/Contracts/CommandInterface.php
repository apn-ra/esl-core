<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for all ESL commands that can be sent to FreeSWITCH.
 *
 * Implementations are responsible for producing valid ESL wire bytes.
 * The serialized form must be terminated with \n\n as required by the protocol.
 */
interface CommandInterface
{
    /**
     * Serialize this command to its ESL wire representation.
     *
     * The returned string MUST end with \n\n.
     */
    public function serialize(): string;
}
