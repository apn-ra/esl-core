<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Protocol\Frame;

/**
 * Contract for all typed reply objects parsed from inbound ESL frames.
 */
interface ReplyInterface
{
    /**
     * Whether this reply indicates a known-success operation on its own contract.
     *
     * A return value of false can mean an explicit protocol error reply, or a
     * conservative "not known-success" degradation case such as UnknownReply.
     */
    public function isSuccess(): bool;

    /**
     * The raw frame this reply was parsed from.
     */
    public function frame(): Frame;
}
