<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

/**
 * Source vocabulary for terminal-publication facts.
 *
 * @api
 */
enum PublicationSource: string
{
    case ProtocolEvent = 'protocol-event';
    case CommandReply = 'command-reply';
    case ReplayEnvelope = 'replay-envelope';
    case CorrelationEnvelope = 'correlation-envelope';
    case DownstreamProjection = 'downstream-projection';
    case Unknown = 'unknown';
}
