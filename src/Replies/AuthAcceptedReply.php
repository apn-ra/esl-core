<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents a successful auth reply from FreeSWITCH.
 *
 * Produced when a command/reply frame with Reply-Text: +OK accepted is received.
 */
final class AuthAcceptedReply implements ReplyInterface
{
    public function __construct(
        private readonly Frame $frame,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
        if ($frame->replyText() !== '+OK accepted') {
            throw new UnexpectedReplyException(
                'AuthAcceptedReply requires Reply-Text: +OK accepted'
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
}
