<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

use Apntalk\EslCore\Contracts\InboundPipelineInterface;
use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;

/**
 * Stable public bootstrap bundle for one prepared inbound connection.
 *
 * This object carries the already-wrapped transport, the supported inbound
 * decode facade, and the per-session correlation context. Runtime read loops,
 * listener ownership, and higher-level orchestration remain outside core.
 *
 * @api
 */
final class PreparedInboundConnection
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly InboundPipelineInterface $pipeline,
        private readonly CorrelationContext $correlationContext,
    ) {}

    public function transport(): TransportInterface
    {
        return $this->transport;
    }

    public function pipeline(): InboundPipelineInterface
    {
        return $this->pipeline;
    }

    public function correlationContext(): CorrelationContext
    {
        return $this->correlationContext;
    }

    public function sessionId(): ConnectionSessionId
    {
        return $this->correlationContext->sessionId();
    }
}
