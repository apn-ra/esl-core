<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Inbound\PreparedInboundConnection;

/**
 * Stable public seam for preparing one accepted inbound ESL connection.
 *
 * Implementations may wrap an already accepted stream into the supported core
 * transport/pipeline/correlation bundle, but they must not assume listener
 * ownership, read loops, reconnect policy, or broader runtime orchestration.
 */
interface InboundConnectionFactoryInterface
{
    /**
     * Prepare a supported inbound connection bundle from an accepted stream.
     *
     * If no session ID is supplied, a new one is generated for the connection.
     *
     * @param resource $stream
     *
     * @throws TransportException when the stream cannot be wrapped as a transport.
     */
    public function prepareAcceptedStream(
        $stream,
        ?ConnectionSessionId $sessionId = null,
    ): PreparedInboundConnection;
}
