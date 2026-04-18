<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal\Classification;

use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\MessageType;

/**
 * Classifies inbound ESL frames into semantic categories.
 *
 * Classification is deterministic: the same frame always yields the same
 * category. Unknown content-types degrade to Unknown rather than throwing.
 *
 * Classification rules:
 *
 * - auth/request                               → ServerAuthRequest
 * - command/reply, Reply-Text: +OK accepted    → AuthAccepted
 * - command/reply, Reply-Text: +OK Job-UUID:   → BgapiAccepted
 * - command/reply, Reply-Text: +OK ...         → CommandAccepted
 * - command/reply, Reply-Text: -ERR ...        → CommandError
 * - api/response                               → ApiResponse
 * - text/event-plain|json|xml                  → EventMessage
 * - text/disconnect-notice                     → DisconnectNotice
 * - anything else                              → Unknown
 *
 * Note: The classifier cannot distinguish auth -ERR from command -ERR
 * purely from the frame alone. Session state is separate, so both cases
 * classify as CommandError at this layer. Higher-level auth/session logic
 * may interpret CommandError differently depending on connection state.
 *
 * @internal Not part of the public API.
 */
final class InboundMessageClassifier implements InboundMessageClassifierInterface
{
    private const JOB_UUID_PREFIX = '+OK Job-UUID: ';

    public function classify(Frame $frame): ClassifiedInboundMessage
    {
        $contentType = $frame->contentType() ?? '';
        $messageType = MessageType::fromContentType($contentType);

        $category = match ($messageType) {
            MessageType::AuthRequest      => InboundMessageCategory::ServerAuthRequest,
            MessageType::CommandReply     => $this->classifyCommandReply($frame),
            MessageType::ApiResponse      => InboundMessageCategory::ApiResponse,
            MessageType::EventPlain,
            MessageType::EventJson,
            MessageType::EventXml         => InboundMessageCategory::EventMessage,
            MessageType::DisconnectNotice => InboundMessageCategory::DisconnectNotice,
            default                       => InboundMessageCategory::Unknown,
        };

        return new ClassifiedInboundMessage($category, $frame, $messageType);
    }

    private function classifyCommandReply(Frame $frame): InboundMessageCategory
    {
        $replyText = $frame->replyText() ?? '';

        if ($replyText === '+OK accepted') {
            return InboundMessageCategory::AuthAccepted;
        }

        if (str_starts_with($replyText, self::JOB_UUID_PREFIX)) {
            return InboundMessageCategory::BgapiAccepted;
        }

        if (str_starts_with($replyText, '+OK')) {
            return InboundMessageCategory::CommandAccepted;
        }

        if (str_starts_with($replyText, '-ERR')) {
            // Cannot distinguish auth rejection from command error here;
            // let the session-state layer or the reply factory refine this.
            return InboundMessageCategory::CommandError;
        }

        return InboundMessageCategory::Unknown;
    }
}
