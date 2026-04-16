<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Events\NormalizedEvent;

/**
 * Explicit contract for event objects that can return the underlying
 * normalized protocol substrate.
 *
 * This additive contract lets callers that already own a typed event reach
 * the shared NormalizedEvent without relying on reflection or concrete
 * property names. It does not change the preferred raw-byte ingress story;
 * callers ingesting bytes should still prefer InboundPipeline and
 * DecodedInboundMessage::normalizedEvent().
 *
 * @api
 */
interface ProvidesNormalizedSubstrateInterface
{
    public function normalized(): NormalizedEvent;
}
