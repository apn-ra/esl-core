<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents an api/response reply from FreeSWITCH.
 *
 * The body of this reply is the raw output of the API command.
 * Success or failure is determined by parsing the body content
 * (e.g., "+OK ...", "-ERR ...", or arbitrary command output).
 */
final class ApiReply implements ReplyInterface
{
    public function __construct(
        private readonly Frame $frame,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
        if ($frame->contentType() !== 'api/response') {
            throw new UnexpectedReplyException(
                'ApiReply requires an api/response frame'
            );
        }

        return new self($frame);
    }

    /**
     * Whether the body starts with "+OK".
     *
     * Many API commands return "+OK ..." on success. However, some commands
     * return non-prefixed output; callers should not rely on isSuccess() for
     * those commands without understanding the specific command's output format.
     */
    public function isSuccess(): bool
    {
        return str_starts_with(ltrim($this->body()), '+OK');
    }

    public function frame(): Frame
    {
        return $this->frame;
    }

    /**
     * The raw API response body.
     */
    public function body(): string
    {
        return $this->frame->body;
    }

    /**
     * The trimmed response body.
     */
    public function trimmedBody(): string
    {
        return rtrim($this->frame->body);
    }
}
