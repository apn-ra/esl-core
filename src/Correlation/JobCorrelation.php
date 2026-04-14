<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Correlation;

use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use InvalidArgumentException;

/**
 * Correlation identifier for asynchronous bgapi flows.
 *
 * The protocol-native key is the Job-UUID assigned by FreeSWITCH:
 * - BgapiAcceptedReply carries it as "the job was accepted with this UUID"
 * - BackgroundJobEvent carries it as "this is the result for that job UUID"
 *
 * The Job-UUID is a FreeSWITCH-assigned protocol identifier. It is NOT
 * generated or interpreted by this package — we treat it as an opaque string
 * that happens to be a UUID.
 *
 * JobCorrelation links these two protocol objects through that identifier.
 * Upper layers may maintain a registry of pending job UUIDs, but that
 * registry lives outside esl-core.
 *
 * @api
 */
final class JobCorrelation
{
    private function __construct(
        private readonly string $jobUuid,
    ) {}

    /**
     * Extract correlation from a BgapiAcceptedReply.
     *
     * Returns null if the reply carries an empty Job-UUID (malformed reply).
     */
    public static function fromBgapiReply(BgapiAcceptedReply $reply): ?self
    {
        $uuid = $reply->jobUuid();

        if ($uuid === '') {
            return null;
        }

        return new self($uuid);
    }

    /**
     * Extract correlation from a BackgroundJobEvent.
     *
     * Returns null if the event carries no Job-UUID header.
     */
    public static function fromBackgroundJobEvent(BackgroundJobEvent $event): ?self
    {
        $uuid = $event->jobUuid();

        if ($uuid === null || $uuid === '') {
            return null;
        }

        return new self($uuid);
    }

    /**
     * Create directly from a Job-UUID string.
     *
     * @throws InvalidArgumentException if $jobUuid is empty.
     */
    public static function fromString(string $jobUuid): self
    {
        if ($jobUuid === '') {
            throw new InvalidArgumentException('JobCorrelation Job-UUID cannot be empty');
        }

        return new self($jobUuid);
    }

    /**
     * The FreeSWITCH-assigned Job-UUID.
     *
     * This is the protocol-native identifier for this async job.
     */
    public function jobUuid(): string
    {
        return $this->jobUuid;
    }

    /**
     * Whether this correlation matches the given Job-UUID string.
     */
    public function matches(string $jobUuid): bool
    {
        return $this->jobUuid === $jobUuid;
    }

    public function equals(self $other): bool
    {
        return $this->jobUuid === $other->jobUuid;
    }
}
