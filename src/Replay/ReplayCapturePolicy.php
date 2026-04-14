<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replay;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;

/**
 * Defines which protocol objects are captured for replay.
 *
 * A capture policy answers: should this reply/event be enveloped?
 *
 * This package provides the policy contract and a default CaptureAll policy.
 * Upper-layer packages may implement selective policies that exclude
 * high-volume or low-value events.
 *
 * @api
 */
final class ReplayCapturePolicy
{
    public function __construct(
        private readonly bool $captureReplies = true,
        private readonly bool $captureEvents  = true,
        /** @var list<string> */
        private readonly array $excludeEventNames = [],
    ) {}

    /**
     * A policy that captures everything.
     */
    public static function captureAll(): self
    {
        return new self(captureReplies: true, captureEvents: true, excludeEventNames: []);
    }

    /**
     * A policy that captures only replies (no events).
     */
    public static function repliesOnly(): self
    {
        return new self(captureReplies: true, captureEvents: false);
    }

    /**
     * A policy that captures only events (no replies).
     */
    public static function eventsOnly(): self
    {
        return new self(captureReplies: false, captureEvents: true);
    }

    public function shouldCaptureReply(ReplyInterface $reply): bool
    {
        return $this->captureReplies;
    }

    public function shouldCaptureEvent(EventInterface $event): bool
    {
        if (!$this->captureEvents) {
            return false;
        }

        return !in_array($event->eventName(), $this->excludeEventNames, true);
    }
}
