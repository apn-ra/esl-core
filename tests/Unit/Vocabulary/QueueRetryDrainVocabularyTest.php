<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Vocabulary;

use Apntalk\EslCore\Vocabulary\DrainPosture;
use Apntalk\EslCore\Vocabulary\InFlightOperationId;
use Apntalk\EslCore\Vocabulary\QueueState;
use Apntalk\EslCore\Vocabulary\RecoveryGenerationId;
use Apntalk\EslCore\Vocabulary\RetryAttempt;
use Apntalk\EslCore\Vocabulary\RetryPosture;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QueueRetryDrainVocabularyTest extends TestCase
{
    public function test_queue_retry_drain_enums_publish_stable_values(): void
    {
        $this->assertSame('in-flight', QueueState::InFlight->value);
        $this->assertSame('retrying', RetryPosture::Retrying->value);
        $this->assertSame('drained', DrainPosture::Drained->value);
    }

    public function test_retry_attempt_is_immutable_truth_not_a_scheduler(): void
    {
        $operation = InFlightOperationId::fromString('op-bgapi-1');
        $attempt = new RetryAttempt($operation, 2, 3, RetryPosture::Retrying);

        $this->assertTrue($attempt->operationId()->equals($operation));
        $this->assertSame(2, $attempt->attempt());
        $this->assertSame(3, $attempt->maxAttempts());
        $this->assertFalse($attempt->isExhausted());
        $this->assertSame([
            'operationId' => 'op-bgapi-1',
            'attempt' => 2,
            'maxAttempts' => 3,
            'posture' => 'retrying',
        ], $attempt->toArray());
    }

    public function test_recovery_generation_identity_accepts_positive_integer_generation(): void
    {
        $generation = RecoveryGenerationId::fromInteger(4);

        $this->assertSame('4', $generation->toString());
    }

    public function test_empty_operation_identity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        InFlightOperationId::fromString(' ');
    }

    public function test_retry_attempt_rejects_impossible_attempt_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RetryAttempt(InFlightOperationId::fromString('op-bgapi-2'), 3, 2, RetryPosture::Retrying);
    }
}
