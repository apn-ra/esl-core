<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Typed terminal-publication truth schema.
 *
 * This value object describes a publication fact. It does not publish,
 * schedule, store, or route anything.
 *
 * @api
 */
final class TerminalPublication
{
    public function __construct(
        private readonly PublicationId $publicationId,
        private readonly FinalityMarker $finality,
        private readonly TerminalCause $terminalCause,
        private readonly PublicationSource $source,
        private readonly int $publishedAtMicros,
        private readonly OrderingIdentity $orderingIdentity,
        private readonly ?CorpusRowIdentity $corpusRowIdentity = null,
        private readonly BoundedVarianceMarker $variance = BoundedVarianceMarker::None,
    ) {
        if ($publishedAtMicros < 1) {
            throw new InvalidArgumentException('Published timestamp must be a positive microsecond timestamp.');
        }
    }

    public function publicationId(): PublicationId
    {
        return $this->publicationId;
    }

    public function finality(): FinalityMarker
    {
        return $this->finality;
    }

    public function terminalCause(): TerminalCause
    {
        return $this->terminalCause;
    }

    public function source(): PublicationSource
    {
        return $this->source;
    }

    public function publishedAtMicros(): int
    {
        return $this->publishedAtMicros;
    }

    public function orderingIdentity(): OrderingIdentity
    {
        return $this->orderingIdentity;
    }

    public function corpusRowIdentity(): ?CorpusRowIdentity
    {
        return $this->corpusRowIdentity;
    }

    public function variance(): BoundedVarianceMarker
    {
        return $this->variance;
    }

    public function isFinal(): bool
    {
        return $this->finality === FinalityMarker::Final;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'publicationId' => $this->publicationId->toString(),
            'finality' => $this->finality->value,
            'terminalCause' => $this->terminalCause->value,
            'source' => $this->source->value,
            'publishedAtMicros' => $this->publishedAtMicros,
            'orderingIdentity' => $this->orderingIdentity->toArray(),
            'corpusRowIdentity' => $this->corpusRowIdentity?->toArray(),
            'variance' => $this->variance->value,
        ];
    }
}
