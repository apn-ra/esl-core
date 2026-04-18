<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * One lifecycle semantic observation for downstream projectors.
 *
 * This is vocabulary only; projection state machines live outside core.
 *
 * @api
 */
final class LifecycleSemanticObservation
{
    public function __construct(
        private readonly LifecycleTransition $transition,
        private readonly LifecycleSemanticState $state,
        private readonly OrderingIdentity $orderingIdentity,
        private readonly ?string $subjectId,
        private readonly BoundedVarianceMarker $variance = BoundedVarianceMarker::None,
    ) {
        if ($subjectId !== null && trim($subjectId) === '') {
            throw new InvalidArgumentException('Lifecycle subject ID must be null or non-empty.');
        }
    }

    public function transition(): LifecycleTransition
    {
        return $this->transition;
    }

    public function state(): LifecycleSemanticState
    {
        return $this->state;
    }

    public function orderingIdentity(): OrderingIdentity
    {
        return $this->orderingIdentity;
    }

    public function subjectId(): ?string
    {
        return $this->subjectId;
    }

    public function variance(): BoundedVarianceMarker
    {
        return $this->variance;
    }

    public function isTerminal(): bool
    {
        return $this->transition === LifecycleTransition::Terminal;
    }

    /**
     * @return array{transition: string, state: string, orderingIdentity: array{source: string, value: string}, subjectId: string|null, variance: string}
     */
    public function toArray(): array
    {
        return [
            'transition' => $this->transition->value,
            'state' => $this->state->value,
            'orderingIdentity' => $this->orderingIdentity->toArray(),
            'subjectId' => $this->subjectId,
            'variance' => $this->variance->value,
        ];
    }
}
