<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Protocol;

/**
 * An ESL protocol frame: a parsed header bag and a raw body.
 *
 * A Frame is purely wire-level. It carries no semantic interpretation —
 * that is the responsibility of the classification layer.
 *
 * The body is a raw byte string. Its meaning depends on the Content-Type:
 * - For text/event-plain: the body contains event headers + optional event body.
 * - For api/response: the body is the API command output.
 * - For text/disconnect-notice: the body may contain inner headers.
 * - For command/reply and auth/request: body is typically empty.
 *
 * @api
 */
final class Frame
{
    public function __construct(
        public readonly HeaderBag $headers,
        public readonly string $body,
    ) {}

    /**
     * The value of the Content-Type header, or null if absent.
     */
    public function contentType(): ?string
    {
        return $this->headers->get('Content-Type');
    }

    /**
     * The declared Content-Length as an integer, or null if the header is absent.
     *
     * Note: this is the declared length, not verified against the actual body.
     * The FrameParser guarantees that body length matches Content-Length before
     * emitting a Frame.
     */
    public function contentLength(): ?int
    {
        $value = $this->headers->get('Content-Length');
        if ($value === null) {
            return null;
        }
        return ctype_digit($value) ? (int) $value : null;
    }

    /**
     * The value of the Reply-Text header, or null if absent.
     * Present on command/reply frames.
     */
    public function replyText(): ?string
    {
        return $this->headers->get('Reply-Text');
    }

    public function hasBody(): bool
    {
        return $this->body !== '';
    }
}
