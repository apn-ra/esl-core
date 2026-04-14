<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Contract for ESL event parsers.
 *
 * Implementations decode supported event frame bodies (`text/event-plain`,
 * `text/event-json`, `text/event-xml`) into a NormalizedEvent, applying
 * source-format normalization as required.
 *
 * Upper-layer integrations should prefer `InboundPipelineInterface` for raw
 * inbound bytes. This lower-level contract remains useful when callers
 * intentionally own frame-level ingestion.
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
