<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;

/**
 * A normalized ESL event parsed from a supported ESL event frame.
 *
 * Provides URL-decoded access to event headers. Raw (encoded) values are
 * accessible via rawHeader() for diagnostic use.
 *
 * Header values in text/event-plain are URL-encoded by FreeSWITCH. JSON event
 * payloads are normalized to the same surface without URL-decoding. This class
 * preserves that distinction internally while exposing one normalized API.
 *
 * @api
 */
final class NormalizedEvent implements EventInterface
{
    public function __construct(
        /** The outer frame headers (Content-Type, Content-Length). */
        public readonly HeaderBag $outerHeaders,
        /** The event-specific headers (parsed from the frame body). */
        public readonly HeaderBag $eventHeaders,
        /** The event body bytes (after the \n\n in the event data). */
        public readonly string $rawBody,
        /** The original frame this event was parsed from. */
        public readonly Frame $frame,
        /** Whether event header values should be URL-decoded on access. */
        private readonly bool $headersAreUrlEncoded = true,
    ) {}

    // ---------------------------------------------------------------------------
    // Core EventInterface accessors (decoded)
    // ---------------------------------------------------------------------------

    public function eventName(): string
    {
        return $this->decoded('Event-Name') ?? '';
    }

    public function uniqueId(): ?string
    {
        // Unique-ID is the primary channel UUID in most events
        return $this->decoded('Unique-ID');
    }

    public function jobUuid(): ?string
    {
        return $this->decoded('Job-UUID');
    }

    public function coreUuid(): ?string
    {
        return $this->decoded('Core-UUID');
    }

    public function eventSequence(): ?string
    {
        // Event-Sequence is a plain integer — no encoding needed, but use
        // the consistent decoded() path for uniformity.
        return $this->decoded('Event-Sequence');
    }

    // ---------------------------------------------------------------------------
    // Extended normalized accessors
    // ---------------------------------------------------------------------------

    public function callDirection(): ?string
    {
        return $this->decoded('Call-Direction');
    }

    public function channelName(): ?string
    {
        return $this->decoded('Channel-Name');
    }

    public function channelState(): ?string
    {
        return $this->decoded('Channel-State');
    }

    public function callerIdName(): ?string
    {
        return $this->decoded('Caller-Caller-ID-Name');
    }

    public function callerIdNumber(): ?string
    {
        return $this->decoded('Caller-Caller-ID-Number');
    }

    public function hangupCause(): ?string
    {
        return $this->decoded('Hangup-Cause');
    }

    public function eventSubclass(): ?string
    {
        return $this->decoded('Event-Subclass');
    }

    public function freeSwitchHostname(): ?string
    {
        return $this->decoded('FreeSWITCH-Hostname');
    }

    public function eventDateTimestamp(): ?string
    {
        return $this->decoded('Event-Date-Timestamp');
    }

    public function jobCommand(): ?string
    {
        return $this->decoded('Job-Command');
    }

    public function otherLegUniqueId(): ?string
    {
        return $this->decoded('Other-Leg-Unique-ID');
    }

    public function otherLegChannelName(): ?string
    {
        return $this->decoded('Other-Leg-Channel-Name');
    }

    public function playbackUuid(): ?string
    {
        return $this->decoded('Playback-UUID');
    }

    public function playbackFilePath(): ?string
    {
        return $this->decoded('Playback-File-Path');
    }

    // ---------------------------------------------------------------------------
    // Generic decoded header access
    // ---------------------------------------------------------------------------

    /**
     * Get a decoded header value by name.
     *
     * Returns null if the header is not present.
     * URL-decodes the value when the source format requires it.
     */
    public function header(string $name): ?string
    {
        return $this->decoded($name);
    }

    /**
     * Get the raw (URL-encoded) header value.
     *
     * Useful for diagnostics or when you need to forward the raw value.
     */
    public function rawHeader(string $name): ?string
    {
        return $this->eventHeaders->get($name);
    }

    /**
     * The event body bytes (e.g., the bgapi result for BACKGROUND_JOB events).
     */
    public function body(): string
    {
        return $this->rawBody;
    }

    /**
     * Whether the event has a non-empty body.
     */
    public function hasBody(): bool
    {
        return $this->rawBody !== '';
    }

    // ---------------------------------------------------------------------------
    // Internal
    // ---------------------------------------------------------------------------

    private function decoded(string $name): ?string
    {
        $raw = $this->eventHeaders->get($name);
        if ($raw === null) {
            return null;
        }

        return $this->headersAreUrlEncoded ? urldecode($raw) : $raw;
    }
}
