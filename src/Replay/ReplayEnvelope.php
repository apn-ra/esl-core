<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replay;

use Apntalk\EslCore\Contracts\ReplayEnvelopeInterface;

/**
 * A replay-safe envelope wrapping a captured protocol object.
 *
 * Preserves enough metadata for deterministic reconstruction by upper layers:
 * - what was captured (type, name)
 * - session context (session ID)
 * - capture ordering (sequence, timestamp)
 * - protocol context (protocol sequence)
 * - raw payload for reconstruction
 * - classifier context for protocol-level reconstruction
 *
 * This package provides the envelope shape and factory.
 * Storage, scheduling, and replay execution live in upper-layer packages.
 *
 * @api
 */
final class ReplayEnvelope implements ReplayEnvelopeInterface
{
    /**
     * @param array<string, string> $classifierContext
     * @param array<string, string> $protocolFacts
     * @param array<string, string> $derivedMetadata
     */
    public function __construct(
        private readonly string $capturedType,
        private readonly string $capturedName,
        private readonly ?string $sessionId,
        private readonly int $captureSequence,
        private readonly int $capturedAtMicros,
        private readonly ?string $protocolSequence,
        private readonly string $rawPayload,
        private readonly array $classifierContext,
        private readonly array $protocolFacts,
        private readonly array $derivedMetadata,
    ) {}

    public function capturedType(): string
    {
        return $this->capturedType;
    }

    public function capturedName(): string
    {
        return $this->capturedName;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function captureSequence(): int
    {
        return $this->captureSequence;
    }

    public function capturedAtMicros(): int
    {
        return $this->capturedAtMicros;
    }

    public function protocolSequence(): ?string
    {
        return $this->protocolSequence;
    }

    public function rawPayload(): string
    {
        return $this->rawPayload;
    }

    public function classifierContext(): array
    {
        return $this->classifierContext;
    }

    public function protocolFacts(): array
    {
        return $this->protocolFacts;
    }

    public function derivedMetadata(): array
    {
        return $this->derivedMetadata;
    }
}
