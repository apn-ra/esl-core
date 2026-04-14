<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents a successful command reply from FreeSWITCH.
 *
 * Produced when a command/reply frame with Reply-Text: +OK ... (not auth, not bgapi)
 * is received. Covers subscribe replies, filter replies, noevents replies, etc.
 */
final class CommandReply implements ReplyInterface
{
    public function __construct(
        private readonly Frame $frame,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
        $replyText = $frame->replyText() ?? '';

        if (!str_starts_with($replyText, '+OK')) {
            throw new UnexpectedReplyException(
                'CommandReply requires a +OK command/reply frame'
            );
        }

        if ($replyText === '+OK accepted' || str_starts_with($replyText, '+OK Job-UUID: ')) {
            throw new UnexpectedReplyException(
                'CommandReply cannot represent auth acceptance or bgapi acceptance replies'
            );
        }

        return new self($frame);
    }

    public function isSuccess(): bool
    {
        return true;
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
     * The reply text after the "+OK" prefix, if any.
     */
    public function message(): string
    {
        $text = $this->replyText();
        if (str_starts_with($text, '+OK ')) {
            return substr($text, 4);
        }
        return '';
    }
}
