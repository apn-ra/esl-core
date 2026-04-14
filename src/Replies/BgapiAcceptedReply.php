<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Exceptions\UnexpectedReplyException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Represents a bgapi acceptance reply from FreeSWITCH.
 *
 * Produced when a command/reply frame with Reply-Text: +OK Job-UUID: <uuid> is received.
 *
 * IMPORTANT: This reply indicates only that the bgapi command was ACCEPTED and a
 * background job was started. The actual job result arrives later as a
 * BACKGROUND_JOB event, correlated by the Job-UUID from this reply.
 */
final class BgapiAcceptedReply implements ReplyInterface
{
    private const JOB_UUID_PREFIX = '+OK Job-UUID: ';

    public function __construct(
        private readonly Frame $frame,
        private readonly string $jobUuid,
    ) {}

    public static function fromFrame(Frame $frame): self
    {
        $replyText = $frame->replyText() ?? '';
        if (!str_starts_with($replyText, self::JOB_UUID_PREFIX)) {
            throw new UnexpectedReplyException(
                'BgapiAcceptedReply requires Reply-Text: +OK Job-UUID: <uuid>'
            );
        }

        $jobUuid = trim(substr($replyText, strlen(self::JOB_UUID_PREFIX)));
        if ($jobUuid === '') {
            throw new UnexpectedReplyException(
                'BgapiAcceptedReply requires a non-empty Job-UUID'
            );
        }

        return new self($frame, $jobUuid);
    }

    public function isSuccess(): bool
    {
        return true;
    }

    public function frame(): Frame
    {
        return $this->frame;
    }

    /**
     * The background job UUID assigned by FreeSWITCH.
     *
     * Use this to correlate the later BACKGROUND_JOB event.
     */
    public function jobUuid(): string
    {
        return $this->jobUuid;
    }

    public function replyText(): string
    {
        return $this->frame->replyText() ?? '';
    }
}
