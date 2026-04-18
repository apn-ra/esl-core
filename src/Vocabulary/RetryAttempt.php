<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Vocabulary;

use InvalidArgumentException;

/**
 * Immutable retry attempt truth; no retry scheduling is implied.
 *
 * @api
 */
final class RetryAttempt
{
    public function __construct(
        private readonly InFlightOperationId $operationId,
        private readonly int $attempt,
        private readonly ?int $maxAttempts,
        private readonly RetryPosture $posture,
    ) {
        if ($attempt < 1) {
            throw new InvalidArgumentException('Retry attempt number must be positive.');
        }

        if ($maxAttempts !== null && $maxAttempts < $attempt) {
            throw new InvalidArgumentException('Retry max attempts must be null or greater than or equal to attempt.');
        }
    }

    public function operationId(): InFlightOperationId
    {
        return $this->operationId;
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function maxAttempts(): ?int
    {
        return $this->maxAttempts;
    }

    public function posture(): RetryPosture
    {
        return $this->posture;
    }

    public function isExhausted(): bool
    {
        return $this->posture === RetryPosture::Exhausted
            || ($this->maxAttempts !== null && $this->attempt >= $this->maxAttempts);
    }

    /**
     * @return array{operationId: string, attempt: int, maxAttempts: int|null, posture: string}
     */
    public function toArray(): array
    {
        return [
            'operationId' => $this->operationId->toString(),
            'attempt' => $this->attempt,
            'maxAttempts' => $this->maxAttempts,
            'posture' => $this->posture->value,
        ];
    }
}
