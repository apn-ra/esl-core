<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal\Classification;

use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\MessageType;

/**
 * The result of classifying an inbound ESL frame.
 *
 * Carries the original Frame alongside its classification results
 * so that higher layers can proceed to typed reply/event parsing
 * without re-examining the raw frame.
 *
 * @internal Not part of the public API.
 */
final class ClassifiedInboundMessage
{
    public function __construct(
        public readonly InboundMessageCategory $category,
        public readonly Frame $frame,
        public readonly MessageType $messageType,
    ) {}

    public function isAuthRequest(): bool
    {
        return $this->category === InboundMessageCategory::ServerAuthRequest;
    }

    public function isAuthAccepted(): bool
    {
        return $this->category === InboundMessageCategory::AuthAccepted;
    }

    public function isAuthRejected(): bool
    {
        return $this->category === InboundMessageCategory::AuthRejected;
    }

    public function isBgapiAccepted(): bool
    {
        return $this->category === InboundMessageCategory::BgapiAccepted;
    }

    public function isCommandAccepted(): bool
    {
        return $this->category === InboundMessageCategory::CommandAccepted;
    }

    public function isCommandError(): bool
    {
        return $this->category === InboundMessageCategory::CommandError;
    }

    public function isApiResponse(): bool
    {
        return $this->category === InboundMessageCategory::ApiResponse;
    }

    public function isEvent(): bool
    {
        return $this->category === InboundMessageCategory::EventMessage;
    }

    public function isDisconnectNotice(): bool
    {
        return $this->category === InboundMessageCategory::DisconnectNotice;
    }

    public function isUnknown(): bool
    {
        return $this->category === InboundMessageCategory::Unknown;
    }
}
