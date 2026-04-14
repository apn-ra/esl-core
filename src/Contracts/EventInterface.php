<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for all typed ESL event objects.
 *
 * Events originate from text/event-plain, text/event-json, or text/event-xml
 * inbound frames. The normalized accessors always return decoded values;
 * the underlying raw header access is available on NormalizedEvent.
 */
interface EventInterface
{
    /**
     * The event name (e.g., CHANNEL_CREATE, BACKGROUND_JOB).
     * Decoded from URL-encoding if applicable.
     */
    public function eventName(): string;

    /**
     * The channel UUID (Unique-ID), if present.
     * Decoded from URL-encoding if applicable.
     */
    public function uniqueId(): ?string;

    /**
     * The background job UUID (Job-UUID), if present.
     */
    public function jobUuid(): ?string;

    /**
     * The FreeSWITCH core UUID, if present.
     */
    public function coreUuid(): ?string;

    /**
     * The event sequence number as a string, if present.
     */
    public function eventSequence(): ?string;
}
