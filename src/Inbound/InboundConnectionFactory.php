<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

use Apntalk\EslCore\Contracts\InboundConnectionFactoryInterface;
use Apntalk\EslCore\Contracts\TransportFactoryInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Transport\SocketTransportFactory;

/**
 * Public accepted-stream bootstrap factory for one inbound ESL connection.
 *
 * This factory intentionally assembles only stable core primitives needed to
 * prepare a connection after a listener/runtime has already accepted a stream.
 *
 * @api
 */
final class InboundConnectionFactory implements InboundConnectionFactoryInterface
{
    public function __construct(
        private readonly ?TransportFactoryInterface $transportFactory = null,
    ) {}

    public function prepareAcceptedStream(
        $stream,
        ?ConnectionSessionId $sessionId = null,
    ): PreparedInboundConnection {
        $transportFactory = $this->transportFactory ?? new SocketTransportFactory();
        $sessionId ??= ConnectionSessionId::generate();

        return new PreparedInboundConnection(
            $transportFactory->fromStream($stream),
            InboundPipeline::withDefaults(),
            new CorrelationContext($sessionId),
        );
    }
}
