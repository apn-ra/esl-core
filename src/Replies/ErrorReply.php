<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents an error reply from FreeSWITCH.
 *
 * Produced when a command/reply frame with Reply-Text: -ERR ... is received.
 * This covers both auth rejections and command errors; the caller must use
 * session state to distinguish them.
 */
final class ErrorReply implements ReplyInterface
{
    public function __construct(
        private readonly Frame $frame,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
        if (!str_starts_with($frame->replyText() ?? '', '-ERR')) {
            throw new UnexpectedReplyException(
                'ErrorReply requires a -ERR command/reply frame'
            );
        }

        return new self($frame);
    }

    public function isSuccess(): bool
    {
        return false;
    }

    public function frame(): Frame
    {
        return $this->frame;
    }

    public function replyText(): string
    {
        return $this->frame->replyText() ?? '';
    }

    /**
     * The error reason after the "-ERR " prefix.
     */
    public function reason(): string
    {
        $text = $this->replyText();
        if (str_starts_with($text, '-ERR ')) {
            return substr($text, 5);
        }
        // Just "-ERR" with no trailing message
        if ($text === '-ERR') {
            return '';
        }
        return $text;
    }
}
