<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replay;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ProvidesNormalizedSubstrateInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\MessageMetadata;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\NormalizedEvent;
use Apntalk\EslCore\Exceptions\ReplayConsistencyException;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;

/**
 * Produces ReplayEnvelope instances from typed protocol objects.
 *
 * @api
 */
final class ReplayEnvelopeFactory
{
    private int $sequence = 0;

    public function __construct(
        private readonly ?string $sessionId = null,
    ) {}

    /**
     * Return a new factory instance bound to the given session identity.
     *
     * The capture sequence resets to zero on the new instance. Use this when
     * constructing a per-session factory from a CorrelationContext session ID.
     */
    public static function withSession(ConnectionSessionId $session): self
    {
        return new self($session->toString());
    }

    /**
     * Wrap a reply in a ReplayEnvelope.
     */
    public function fromReply(ReplyInterface $reply): ReplayEnvelope
    {
        return $this->buildReplyEnvelope($reply, null);
    }

    /**
     * Wrap a correlation-layer reply envelope in a ReplayEnvelope.
     */
    public function fromReplyEnvelope(ReplyEnvelope $envelope): ReplayEnvelope
    {
        return $this->buildReplyEnvelope($envelope->reply(), $envelope->metadata());
    }

    /**
     * Wrap a normalized event in a ReplayEnvelope.
     */
    public function fromNormalizedEvent(NormalizedEvent $event): ReplayEnvelope
    {
        return $this->buildEventEnvelope($event, null);
    }

    /**
     * Wrap a typed event in a ReplayEnvelope.
     * If the event wraps a NormalizedEvent, uses its payload; otherwise falls back.
     */
    public function fromEvent(EventInterface $event): ReplayEnvelope
    {
        return $this->buildTypedEventEnvelope($event, null);
    }

    /**
     * Wrap a correlation-layer event envelope in a ReplayEnvelope.
     */
    public function fromEventEnvelope(EventEnvelope $envelope): ReplayEnvelope
    {
        return $this->buildTypedEventEnvelope($envelope->event(), $envelope->metadata());
    }

    private function buildReplyEnvelope(ReplyInterface $reply, ?MessageMetadata $metadata): ReplayEnvelope
    {
        $frame = $reply->frame();
        $name  = get_class($reply);
        $name = substr($name, strrpos($name, '\\') + 1);
        $jobUuid = $reply instanceof BgapiAcceptedReply ? $reply->jobUuid() : null;

        return new ReplayEnvelope(
            capturedType: 'reply',
            capturedName: $name,
            sessionId: $this->resolveSessionId($metadata),
            captureSequence: $this->resolveCaptureSequence($metadata),
            capturedAtMicros: $this->resolveCapturedAtMicros($metadata),
            protocolSequence: null,
            rawPayload: $this->replyPayload($reply),
            classifierContext: [
                'content-type' => $frame->contentType() ?? '',
                'reply-text'   => $frame->replyText() ?? '',
            ],
            protocolFacts: $this->filterFacts([
                'content-type' => $frame->contentType() ?? '',
                'reply-text'   => $frame->replyText() ?? '',
                'job-uuid'     => $jobUuid ?? '',
            ]),
            derivedMetadata: $this->derivedMetadata($metadata),
        );
    }

    private function buildEventEnvelope(NormalizedEvent $event, ?MessageMetadata $metadata): ReplayEnvelope
    {
        return new ReplayEnvelope(
            capturedType: 'event',
            capturedName: $event->eventName(),
            sessionId: $this->resolveSessionId($metadata),
            captureSequence: $this->resolveCaptureSequence($metadata),
            capturedAtMicros: $this->resolveCapturedAtMicros($metadata),
            protocolSequence: $event->eventSequence(),
            rawPayload: $event->frame->body,
            classifierContext: [
                'content-type' => $event->outerHeaders->get('Content-Type') ?? '',
                'event-name'   => $event->eventName(),
                'unique-id'    => $event->uniqueId() ?? '',
            ],
            protocolFacts: $this->filterFacts([
                'content-type'         => $event->outerHeaders->get('Content-Type') ?? '',
                'event-name'           => $event->eventName(),
                'event-sequence'       => $event->eventSequence() ?? '',
                'event-date-timestamp' => $event->eventDateTimestamp() ?? '',
                'core-uuid'            => $event->coreUuid() ?? '',
                'unique-id'            => $event->uniqueId() ?? '',
                'job-uuid'             => $event->jobUuid() ?? '',
            ]),
            derivedMetadata: $this->derivedMetadata($metadata),
        );
    }

    private function buildTypedEventEnvelope(EventInterface $event, ?MessageMetadata $metadata): ReplayEnvelope
    {
        $normalized = $this->extractNormalized($event);
        if ($normalized !== null) {
            return $this->buildEventEnvelope($normalized, $metadata);
        }

        return new ReplayEnvelope(
            capturedType: 'event',
            capturedName: $event->eventName(),
            sessionId: $this->resolveSessionId($metadata),
            captureSequence: $this->resolveCaptureSequence($metadata),
            capturedAtMicros: $this->resolveCapturedAtMicros($metadata),
            protocolSequence: $event->eventSequence(),
            rawPayload: '',
            classifierContext: [
                'event-name' => $event->eventName(),
            ],
            protocolFacts: $this->filterFacts([
                'event-name'     => $event->eventName(),
                'event-sequence' => $event->eventSequence() ?? '',
                'unique-id'      => $event->uniqueId() ?? '',
                'job-uuid'       => $event->jobUuid() ?? '',
                'core-uuid'      => $event->coreUuid() ?? '',
            ]),
            derivedMetadata: $this->derivedMetadata($metadata),
        );
    }

    private function replyPayload(ReplyInterface $reply): string
    {
        $frame = $reply->frame();
        $lines = '';

        foreach ($frame->headers->toFlatArray() as $header) {
            $lines .= "{$header['name']}: {$header['value']}\n";
        }

        return $lines . "\n" . $frame->body;
    }

    private function extractNormalized(EventInterface $event): ?NormalizedEvent
    {
        if ($event instanceof ProvidesNormalizedSubstrateInterface) {
            return $event->normalized();
        }

        return null;
    }

    private function nextSequence(): int
    {
        return ++$this->sequence;
    }

    private function resolveSessionId(?MessageMetadata $metadata): ?string
    {
        $metadataSession = $metadata?->sessionId()?->toString();

        if ($this->sessionId !== null && $metadataSession !== null && $this->sessionId !== $metadataSession) {
            throw new ReplayConsistencyException(
                'ReplayEnvelopeFactory session ID does not match correlation metadata session ID'
            );
        }

        return $metadataSession ?? $this->sessionId;
    }

    private function resolveCaptureSequence(?MessageMetadata $metadata): int
    {
        return $metadata?->observationSequence()->position() ?? $this->nextSequence();
    }

    private function resolveCapturedAtMicros(?MessageMetadata $metadata): int
    {
        return $metadata?->observedAtMicros() ?? $this->nowMicros();
    }

    /**
     * @return array<string, string>
     */
    private function derivedMetadata(?MessageMetadata $metadata): array
    {
        if ($metadata === null) {
            return [];
        }

        $job = $metadata->jobCorrelation();
        $channel = $metadata->channelCorrelation();

        return $this->filterFacts([
            'session-id'                  => $metadata->sessionId()?->toString() ?? '',
            'observation-sequence'        => (string) $metadata->observationSequence()->position(),
            'observed-at-micros'          => (string) $metadata->observedAtMicros(),
            'job-correlation.job-uuid'    => $job?->jobUuid() ?? '',
            'channel-correlation.unique-id' => $channel?->uniqueId() ?? '',
            'channel-correlation.channel-name' => $channel?->channelName() ?? '',
            'channel-correlation.call-direction' => $channel?->callDirection() ?? '',
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

    private function nowMicros(): int
    {
        return (int) (microtime(true) * 1_000_000);
    }
}
