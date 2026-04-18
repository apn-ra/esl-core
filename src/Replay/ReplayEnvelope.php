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
    public const SCHEMA_VERSION = 'replay-envelope.v1';

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

    public function schemaVersion(): string
    {
        return self::SCHEMA_VERSION;
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

    public function identityFacts(): array
    {
        return $this->filterFacts([
            'schema-version' => $this->schemaVersion(),
            'captured-type' => $this->capturedType,
            'captured-name' => $this->capturedName,
            'session-id' => $this->sessionId ?? '',
            'content-type' => $this->protocolFacts['content-type'] ?? '',
            'event-name' => $this->protocolFacts['event-name'] ?? '',
            'core-uuid' => $this->protocolFacts['core-uuid'] ?? '',
            'unique-id' => $this->protocolFacts['unique-id'] ?? '',
            'job-uuid' => $this->protocolFacts['job-uuid'] ?? '',
        ]);
    }

    public function orderingFacts(): array
    {
        return $this->filterFacts([
            'capture-sequence' => (string) $this->captureSequence,
            'captured-at-micros' => (string) $this->capturedAtMicros,
            'protocol-sequence' => $this->protocolSequence ?? '',
            'event-date-timestamp' => $this->protocolFacts['event-date-timestamp'] ?? '',
            'observation-sequence' => $this->derivedMetadata['observation-sequence'] ?? '',
            'observed-at-micros' => $this->derivedMetadata['observed-at-micros'] ?? '',
        ]);
    }

    public function causalMetadata(): array
    {
        return $this->filterFacts([
            'reply-text' => $this->protocolFacts['reply-text'] ?? '',
            'event-name' => $this->protocolFacts['event-name'] ?? '',
            'job-uuid' => $this->protocolFacts['job-uuid'] ?? '',
            'job-correlation.job-uuid' => $this->derivedMetadata['job-correlation.job-uuid'] ?? '',
            'channel-correlation.unique-id' => $this->derivedMetadata['channel-correlation.unique-id'] ?? '',
            'channel-correlation.channel-name' => $this->derivedMetadata['channel-correlation.channel-name'] ?? '',
            'channel-correlation.call-direction' => $this->derivedMetadata['channel-correlation.call-direction'] ?? '',
        ]);
    }

    /**
     * @param array<string, string> $facts
     * @return array<string, string>
     */
    private function filterFacts(array $facts): array
    {
        return array_filter(
            $facts,
            static fn(string $value): bool => $value !== ''
        );
    }
}
