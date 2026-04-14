<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents an unclassified reply.
 *
 * This is the safe degradation case for frames that do not match any
 * known reply pattern. It allows the upper layer to decide how to handle
 * the unknown frame rather than silently ignoring or throwing.
 */
final class UnknownReply implements ReplyInterface
{
    public function __construct(
        private readonly Frame $frame,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
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

    public function contentType(): ?string
    {
        return $this->frame->contentType();
    }
}
