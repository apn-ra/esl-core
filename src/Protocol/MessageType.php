<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Protocol;

/**
 * ESL Content-Type enumeration.
 *
 * Maps Content-Type header values to typed categories.
 * Unknown content-types degrade to Unknown — they never throw.
 *
 * @internal Wire-layer type. Part of the classification layer input, not public API.
 */
enum MessageType: string
{
    /** Server → Client: FreeSWITCH requests authentication. */
    case AuthRequest = 'auth/request';

    /** Server → Client: Response to most commands (auth, subscribe, filter, etc.). */
    case CommandReply = 'command/reply';

    /** Server → Client: Response to 'api' commands. */
    case ApiResponse = 'api/response';

    /** Server → Client: Event in plain text (URL-encoded) format. */
    case EventPlain = 'text/event-plain';

    /** Server → Client: Event in JSON format. */
    case EventJson = 'text/event-json';

    /** Server → Client: Event in XML format. */
    case EventXml = 'text/event-xml';

    /** Server → Client: Connection will be closed. */
    case DisconnectNotice = 'text/disconnect-notice';

    /** Server → Client: Round-trip time test response. */
    case RttTest = 'text/rtt-test';

    /**
     * An unrecognized or missing content-type.
     * Classification degrades to this rather than throwing.
     */
    case Unknown = 'unknown';

    /**
     * Parse a content-type string into a MessageType.
     * Trims whitespace and lowercases. Never throws.
     */
    public static function fromContentType(string $contentType): self
    {
        $normalized = strtolower(trim($contentType));
        return self::tryFrom($normalized) ?? self::Unknown;
    }

    public function isEvent(): bool
    {
        return match ($this) {
            self::EventPlain, self::EventJson, self::EventXml => true,
            default => false,
        };
    }

    public function isCommandReply(): bool
    {
        return $this === self::CommandReply;
    }

    public function isApiResponse(): bool
    {
        return $this === self::ApiResponse;
    }

    public function isAuthRequest(): bool
    {
        return $this === self::AuthRequest;
    }
}
