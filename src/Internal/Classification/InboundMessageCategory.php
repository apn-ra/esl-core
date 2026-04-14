<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal\Classification;

/**
 * Semantic categories for classified inbound ESL messages.
 *
 * @internal Not part of the public API.
 */
enum InboundMessageCategory
{
    /** Server sent auth/request — client must authenticate. */
    case ServerAuthRequest;

    /** command/reply with +OK accepted — auth was successful. */
    case AuthAccepted;

    /** command/reply with -ERR — auth was rejected. */
    case AuthRejected;

    /** command/reply with +OK Job-UUID: ... — bgapi was accepted, job is running. */
    case BgapiAccepted;

    /** command/reply with +OK ... — a non-bgapi command succeeded. */
    case CommandAccepted;

    /** command/reply with -ERR — a non-auth command failed. */
    case CommandError;

    /** api/response — response to an 'api' command. */
    case ApiResponse;

    /** text/event-plain, text/event-json, or text/event-xml — an inbound event. */
    case EventMessage;

    /** text/disconnect-notice — server is closing the connection. */
    case DisconnectNotice;

    /**
     * Unrecognized content-type or content that does not fit any known category.
     * This must not throw; it is the safe degradation case.
     */
    case Unknown;
}
