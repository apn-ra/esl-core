<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

/**
 * Supported high-level inbound message kinds exposed by the stable facade.
 *
 * @api
 */
enum InboundMessageType: string
{
    case ServerAuthRequest = 'server-auth-request';
    case Reply = 'reply';
    case Event = 'event';
    case DisconnectNotice = 'disconnect-notice';
    case Unknown = 'unknown';
}
