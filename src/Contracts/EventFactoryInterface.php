<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Events\NormalizedEvent;

/**
 * Contract for event factories that produce typed event objects.
 *
 * Implementations take a NormalizedEvent and return the most specific
 * typed event subtype available. Unknown event names MUST degrade to
 * a RawEvent rather than throwing.
 *
 * This contract remains appropriate when a caller already owns a
 * `NormalizedEvent`. For raw inbound byte ingestion, prefer
 * `InboundPipelineInterface`.
 */
interface EventFactoryInterface
{
    /**
     * Produce a typed event from a normalized event.
     *
     * Returns a typed subclass if the event name is recognized,
     * otherwise returns a RawEvent wrapping the normalized event.
     */
    public function fromNormalized(NormalizedEvent $event): EventInterface;
}
