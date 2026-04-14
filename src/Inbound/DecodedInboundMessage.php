<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Events\NormalizedEvent;
use LogicException;

/**
 * One inbound ESL message decoded through the stable public pipeline facade.
 *
 * This value object preserves the typed reply/event result when available,
 * exposes the normalized event substrate for event messages, and keeps
 * auth-request / disconnect / unknown notices explicit without requiring
 * callers to reach into provisional classifier internals.
 *
 * @api
 */
final class DecodedInboundMessage
{
    private function __construct(
        private readonly InboundMessageType $type,
        private readonly ?ReplyInterface $decodedReply = null,
        private readonly ?NormalizedEvent $decodedNormalizedEvent = null,
        private readonly ?EventInterface $decodedEvent = null,
    ) {
        if ($this->decodedEvent !== null && $this->decodedNormalizedEvent === null) {
            throw new LogicException('Typed inbound events require a normalized event substrate.');
        }
    }

    public static function forServerAuthRequest(): self
    {
        return new self(InboundMessageType::ServerAuthRequest);
    }

    public static function forReply(ReplyInterface $reply): self
    {
        return new self(InboundMessageType::Reply, decodedReply: $reply);
    }

    public static function forEvent(NormalizedEvent $normalizedEvent, EventInterface $event): self
    {
        return new self(
            InboundMessageType::Event,
            decodedNormalizedEvent: $normalizedEvent,
            decodedEvent: $event,
        );
    }

    public static function forDisconnectNotice(): self
    {
        return new self(InboundMessageType::DisconnectNotice);
    }

    public static function forUnknown(?ReplyInterface $reply = null): self
    {
        return new self(InboundMessageType::Unknown, decodedReply: $reply);
    }

    public function type(): InboundMessageType
    {
        return $this->type;
    }

    public function isServerAuthRequest(): bool
    {
        return $this->type === InboundMessageType::ServerAuthRequest;
    }

    public function isReply(): bool
    {
        return $this->type === InboundMessageType::Reply;
    }

    public function isEvent(): bool
    {
        return $this->type === InboundMessageType::Event;
    }

    public function isDisconnectNotice(): bool
    {
        return $this->type === InboundMessageType::DisconnectNotice;
    }

    public function isUnknown(): bool
    {
        return $this->type === InboundMessageType::Unknown;
    }

    public function reply(): ?ReplyInterface
    {
        return $this->decodedReply;
    }

    public function normalizedEvent(): ?NormalizedEvent
    {
        return $this->decodedNormalizedEvent;
    }

    public function event(): ?EventInterface
    {
        return $this->decodedEvent;
    }

    public function contentType(): ?string
    {
        if ($this->decodedReply !== null) {
            return $this->decodedReply->frame()->contentType();
        }

        if ($this->decodedNormalizedEvent !== null) {
            return $this->decodedNormalizedEvent->sourceContentType();
        }

        return null;
    }
}
