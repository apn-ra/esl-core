<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Contract for ESL event parsers.
 *
 * Implementations decode a text/event-plain (or similar) frame body
 * into a NormalizedEvent, URL-decoding header values as required.
 */
interface EventParserInterface
{
    /**
     * Parse an inbound event frame into a NormalizedEvent.
     *
     * @throws ParseException if the frame is not a recognized event content-type,
     *                        or if the event body is structurally malformed.
     */
    public function parse(Frame $frame): NormalizedEvent;
}
