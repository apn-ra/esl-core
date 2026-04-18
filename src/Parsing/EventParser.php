<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Parsing;

use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\UnsupportedContentTypeException;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;
use DOMDocument;
use DOMElement;
use DOMNode;
use JsonException;

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
    private const CONTENT_TYPE_EVENT_XML = 'text/event-xml';

    public function parse(Frame $frame): NormalizedEvent
    {
        $contentType = $frame->contentType() ?? '';

        return match ($contentType) {
            self::CONTENT_TYPE_EVENT_PLAIN => $this->parsePlainEvent($frame),
            self::CONTENT_TYPE_EVENT_JSON => $this->parseJsonEvent($frame),
            self::CONTENT_TYPE_EVENT_XML => $this->parseXmlEvent($frame),
            default => throw new UnsupportedContentTypeException(
                "EventParser only handles text/event-plain, text/event-json, and text/event-xml; got: {$contentType}"
            ),
        };
    }

    private function parsePlainEvent(Frame $frame): NormalizedEvent
    {
        $body = $frame->body;

        $delimPos = strpos($body, "\n\n");

        if ($delimPos === false) {
            throw new MalformedFrameException(
                'Plain event payload must contain an inner header terminator'
            );
        }

        $eventHeaderBlock = substr($body, 0, $delimPos);
        $eventBody        = substr($body, $delimPos + 2);

        $eventHeaders = $this->parseHeaderBlock($eventHeaderBlock);
        $this->assertDeclaredBodyLengthMatches($eventHeaders, $eventBody);

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody: $eventBody,
            frame: $frame,
        );
    }

    private function parseJsonEvent(Frame $frame): NormalizedEvent
    {
        try {
            $decoded = json_decode($frame->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
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
        $this->assertDeclaredBodyLengthMatches($eventHeaders, $eventBody);

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody: $eventBody,
            frame: $frame,
            headersAreUrlEncoded: false,
        );
    }

    private function parseXmlEvent(Frame $frame): NormalizedEvent
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadXML($frame->body, LIBXML_NONET | LIBXML_NOBLANKS);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false || !$document->documentElement instanceof DOMElement) {
            $message = $errors !== [] ? trim($errors[0]->message) : 'Unknown XML parse failure';

            throw new MalformedFrameException(
                'Failed to parse event XML: ' . $message
            );
        }

        $root = $document->documentElement;
        if ($root->tagName !== 'event') {
            throw new MalformedFrameException('Event XML root element must be <event>');
        }

        $headersElement = null;
        $bodyElement = null;

        foreach ($root->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            if ($child->tagName === 'headers') {
                if ($headersElement instanceof DOMElement) {
                    throw new MalformedFrameException('Event XML may contain only one <headers> element');
                }

                $headersElement = $child;
                continue;
            }

            if ($child->tagName === 'body') {
                if ($bodyElement instanceof DOMElement) {
                    throw new MalformedFrameException('Event XML may contain only one <body> element');
                }

                $bodyElement = $child;
                continue;
            }

            throw new MalformedFrameException(
                sprintf('Unsupported Event XML element <%s> under <event>', $child->tagName)
            );
        }

        if (!$headersElement instanceof DOMElement) {
            throw new MalformedFrameException('Event XML must contain a <headers> element');
        }

        $headers = [];

        foreach ($headersElement->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            $this->assertNoElementChildren($child, 'Event XML header');

            $name = trim($child->tagName);
            if ($name === '') {
                throw new MalformedFrameException('Event XML header names must be non-empty');
            }

            $value = $child->textContent;
            if (str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new MalformedFrameException(
                    sprintf('Event XML header "%s" may not contain newlines', $name)
                );
            }

            $headers[$name] = $value;
        }

        $eventHeaders = $this->parseHeaderBlock($this->headerBlockFromMap($headers));

        $eventBody = '';
        if ($bodyElement instanceof DOMElement) {
            $this->assertNoElementChildren($bodyElement, 'Event XML body');
            $eventBody = $bodyElement->textContent;
        }

        $this->assertDeclaredBodyLengthMatches($eventHeaders, $eventBody);

        return new NormalizedEvent(
            outerHeaders: $frame->headers,
            eventHeaders: $eventHeaders,
            rawBody: $eventBody,
            frame: $frame,
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

    private function assertDeclaredBodyLengthMatches(HeaderBag $eventHeaders, string $eventBody): void
    {
        $contentLength = $eventHeaders->get('Content-Length');
        if ($contentLength === null) {
            return;
        }

        if (!ctype_digit($contentLength)) {
            throw new MalformedFrameException(
                sprintf('Event Content-Length must be numeric; got: %s', $contentLength)
            );
        }

        $declaredLength = $this->parseDeclaredBodyLength($contentLength);
        $actualLength = strlen($eventBody);

        if ($actualLength !== $declaredLength) {
            throw new MalformedFrameException(
                sprintf(
                    'Event body length mismatch: Content-Length declared %d bytes, got %d bytes',
                    $declaredLength,
                    $actualLength
                )
            );
        }
    }

    private function parseDeclaredBodyLength(string $value): int
    {
        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return 0;
        }

        $max = (string) PHP_INT_MAX;
        if (strlen($normalized) > strlen($max) || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            throw new MalformedFrameException(
                sprintf('Event Content-Length exceeds supported integer range; got: %s', $value)
            );
        }

        return (int) $normalized;
    }

    private function assertNoElementChildren(DOMElement $element, string $context): void
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                throw new MalformedFrameException(
                    sprintf('%s <%s> must not contain nested elements', $context, $element->tagName)
                );
            }

            if (
                $child instanceof DOMNode
                && $child->nodeType === XML_CDATA_SECTION_NODE
                && str_contains($child->textContent, ']]>')
            ) {
                throw new MalformedFrameException(
                    sprintf('%s <%s> contains invalid CDATA termination', $context, $element->tagName)
                );
            }
        }
    }
}
