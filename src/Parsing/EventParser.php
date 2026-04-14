<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Parsing;

use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Exceptions\UnsupportedContentTypeException;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;

/**
 * Parses text/event-plain frames into NormalizedEvent objects.
 *
 * ESL text/event-plain frame body structure:
 *
 *   Key: URL-encoded-Value\n
 *   Key: URL-encoded-Value\n
 *   \n
 *   [optional event body, if Content-Length header is present in event headers]
 *
 * The outer frame's Content-Length already encompasses all bytes (event headers
 * + separator + event body). The outer FrameParser ensures all bytes are present
 * before emitting the frame. EventParser only needs to split at the first \n\n.
 *
 * URL-decoding of event header values is NOT done here — it is done in
 * NormalizedEvent's accessors. EventParser preserves raw header values.
 */
final class EventParser implements EventParserInterface
{
    private const SUPPORTED_CONTENT_TYPES = ['text/event-plain'];

    public function parse(Frame $frame): NormalizedEvent
    {
        $contentType = $frame->contentType() ?? '';

        if (!in_array($contentType, self::SUPPORTED_CONTENT_TYPES, true)) {
            throw new UnsupportedContentTypeException(
                "EventParser only handles text/event-plain; got: {$contentType}"
            );
        }

        $body = $frame->body;

        // Split event data at the first \n\n: headers on the left, body on the right.
        $delimPos = strpos($body, "\n\n");

        if ($delimPos === false) {
            // No \n\n found — treat entire body as event headers with empty body.
            // Trim trailing \n that some events emit without a full separator.
            $eventHeaderBlock = rtrim($body, "\n");
            $eventBody        = '';
        } else {
            $eventHeaderBlock = substr($body, 0, $delimPos);
            $eventBody        = substr($body, $delimPos + 2);
        }

        try {
            $eventHeaders = HeaderBag::fromHeaderBlock($eventHeaderBlock);
        } catch (\Apntalk\EslCore\Exceptions\ParseException $e) {
            throw new MalformedFrameException(
                "Failed to parse event headers: " . $e->getMessage(),
                previous: $e,
            );
        }

        if ($eventHeaders->isEmpty()) {
            throw new MalformedFrameException(
                "Parsed event has no headers — frame body may be malformed or empty"
            );
        }

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody:      $eventBody,
            frame:        $frame,
        );
    }
}
