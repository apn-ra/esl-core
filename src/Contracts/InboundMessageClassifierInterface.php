<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Contract for inbound message classifiers.
 *
 * A classifier takes a raw Frame and determines its protocol category:
 * auth request, command reply, API response, event, or disconnect notice.
 *
 * Classification must be deterministic: the same frame always produces
 * the same category.
 *
 * Classification must not fail for unknown content-types; instead it
 * must degrade to Unknown.
 *
 * Upper-layer integrations should prefer `InboundPipelineInterface` for the
 * supported ingress path. This lower-level contract remains provisional and
 * primarily exists for internal composition and targeted tests.
 *
 * The concrete return type remains the current internal classified-message
 * carrier for compatibility in this release line. Advanced callers that want
 * to stay on a public read-only contract should treat the result as
 * `ClassifiedMessageInterface` or prefer `InboundPipelineInterface`.
 */
interface InboundMessageClassifierInterface
{
    /**
     * Classify an inbound frame.
     *
     * Never throws for unknown content-types. Degrades to Unknown category.
     */
    public function classify(Frame $frame): ClassifiedInboundMessage;
}
