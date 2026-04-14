<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Events;

use Apntalk\EslCore\Contracts\EventFactoryInterface;
use Apntalk\EslCore\Contracts\EventInterface;
use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Produces typed EventInterface instances from text/event-plain frames.
 *
 * Combines EventParser (frame → NormalizedEvent) with EventClassifier
 * (NormalizedEvent → typed EventInterface).
 *
 * @api
 */
final class EventFactory implements EventFactoryInterface
{
    private readonly EventParserInterface $parser;
    private readonly EventClassifier $classifier;

    public function __construct(
        ?EventParserInterface $parser = null,
        ?EventClassifier $classifier = null,
    ) {
        $this->parser     = $parser ?? new EventParser();
        $this->classifier = $classifier ?? new EventClassifier();
    }

    /**
     * Parse a text/event-plain frame and return a typed event.
     */
    public function fromFrame(Frame $frame): EventInterface
    {
        $normalized = $this->parser->parse($frame);
        return $this->classifier->classify($normalized);
    }

    /**
     * Produce a typed event from a pre-parsed NormalizedEvent.
     */
    public function fromNormalized(NormalizedEvent $event): EventInterface
    {
        return $this->classifier->classify($event);
    }
}
