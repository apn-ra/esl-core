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
     * Whether this reply indicates a successful operation.
     */
    public function isSuccess(): bool;

    /**
     * The raw frame this reply was parsed from.
     */
    public function frame(): Frame;
}
