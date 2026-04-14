<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

/**
 * Contract for replay-safe protocol envelopes.
 *
 * A replay envelope wraps a protocol object (reply or event) with enough
 * metadata to support deterministic reconstruction and audit by upper layers.
 *
 * This package provides the envelope shape and capture contracts.
 * It does NOT provide a replay runtime, storage engine, or scheduler.
 */
interface ReplayEnvelopeInterface
{
    /**
     * The type identifier for the captured object (e.g., 'reply', 'event').
     */
    public function capturedType(): string;

    /**
     * The event or reply name (e.g., 'CHANNEL_CREATE', 'AuthAccepted').
     */
    public function capturedName(): string;

    /**
     * The session ID this envelope belongs to, if known.
     */
    public function sessionId(): ?string;

    /**
     * Monotonically increasing capture sequence within a session.
     */
    public function captureSequence(): int;

    /**
     * Wall-clock capture timestamp in microseconds since epoch.
     */
    public function capturedAtMicros(): int;

    /**
     * The event sequence number from the FreeSWITCH protocol, if present.
     */
    public function protocolSequence(): ?string;

    /**
     * Serialized payload suitable for reconstruction.
     *
     * For events: the raw event body from the frame.
     * For replies: the raw header block from the frame.
     */
    public function rawPayload(): string;

    /**
     * Additional classifier context needed for reconstruction.
     *
     * @return array<string, string>
     */
    public function classifierContext(): array;

    /**
     * Protocol-native facts preserved separately from derived metadata.
     *
     * @return array<string, string>
     */
    public function protocolFacts(): array;

    /**
     * Derived metadata assigned by esl-core rather than carried on the wire.
     *
     * @return array<string, string>
     */
    public function derivedMetadata(): array;
}
