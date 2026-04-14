<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Parsing;

use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\UnsupportedContentTypeException;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;

/**
 * Parses supported ESL event frames into NormalizedEvent objects.
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
 * For text/event-json, the outer frame body must decode to a JSON object where
 * top-level scalar entries become normalized event headers and optional `_body`
 * becomes the event body. Nested values are rejected to preserve deterministic,
 * protocol-truthful normalization.
 */
final class EventParser implements EventParserInterface
{
    private const CONTENT_TYPE_EVENT_PLAIN = 'text/event-plain';
    private const CONTENT_TYPE_EVENT_JSON = 'text/event-json';

    public function parse(Frame $frame): NormalizedEvent
    {
        $contentType = $frame->contentType() ?? '';

        return match ($contentType) {
            self::CONTENT_TYPE_EVENT_PLAIN => $this->parsePlainEvent($frame),
            self::CONTENT_TYPE_EVENT_JSON => $this->parseJsonEvent($frame),
            default => throw new UnsupportedContentTypeException(
                "EventParser only handles text/event-plain and text/event-json; got: {$contentType}"
            ),
        };
    }

    private function parsePlainEvent(Frame $frame): NormalizedEvent
    {
        $body = $frame->body;

        $delimPos = strpos($body, "\n\n");

        if ($delimPos === false) {
            $eventHeaderBlock = rtrim($body, "\n");
            $eventBody        = '';
        } else {
            $eventHeaderBlock = substr($body, 0, $delimPos);
            $eventBody        = substr($body, $delimPos + 2);
        }

        $eventHeaders = $this->parseHeaderBlock($eventHeaderBlock);

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody:      $eventBody,
            frame:        $frame,
        );
    }

    private function parseJsonEvent(Frame $frame): NormalizedEvent
    {
        try {
            $decoded = json_decode($frame->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MalformedFrameException(
                'Failed to parse event JSON: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new MalformedFrameException(
                'Event JSON must decode to an object-like map of headers'
            );
        }

        $eventBody = '';
        if (array_key_exists('_body', $decoded)) {
            if (!is_string($decoded['_body'])) {
                throw new MalformedFrameException('Event JSON _body must be a string');
            }
            $eventBody = $decoded['_body'];
            unset($decoded['_body']);
        }

        $headers = [];
        foreach ($decoded as $name => $value) {
            if (!is_string($name) || $name === '') {
                throw new MalformedFrameException('Event JSON header names must be non-empty strings');
            }

            if (is_array($value) || is_object($value) || $value === null) {
                throw new MalformedFrameException(
                    sprintf('Event JSON header "%s" must be a scalar string value', $name)
                );
            }

            $stringValue = (string) $value;
            if (str_contains($stringValue, "\n") || str_contains($stringValue, "\r")) {
                throw new MalformedFrameException(
                    sprintf('Event JSON header "%s" may not contain newlines', $name)
                );
            }

            $headers[$name] = $stringValue;
        }

        $eventHeaders = $this->parseHeaderBlock($this->headerBlockFromMap($headers));

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody:      $eventBody,
            frame:        $frame,
            headersAreUrlEncoded: false,
        );
    }

    private function parseHeaderBlock(string $eventHeaderBlock): HeaderBag
    {
        try {
            $eventHeaders = HeaderBag::fromHeaderBlock($eventHeaderBlock);
        } catch (\Apntalk\EslCore\Exceptions\ParseException $e) {
            throw new MalformedFrameException(
                'Failed to parse event headers: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if ($eventHeaders->isEmpty()) {
            throw new MalformedFrameException(
                'Parsed event has no headers — frame body may be malformed or empty'
            );
        }

        return $eventHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    private function headerBlockFromMap(array $headers): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = sprintf('%s: %s', $name, $value);
        }

        return implode("\n", $lines);
    }
}
