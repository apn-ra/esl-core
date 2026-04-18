<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Vocabulary;

use Apntalk\EslCore\Vocabulary\BoundedVarianceMarker;
use Apntalk\EslCore\Vocabulary\LifecycleSemanticObservation;
use Apntalk\EslCore\Vocabulary\LifecycleSemanticState;
use Apntalk\EslCore\Vocabulary\LifecycleTransition;
use Apntalk\EslCore\Vocabulary\OrderingIdentity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LifecycleSemanticObservationTest extends TestCase
{
    public function test_lifecycle_semantic_observation_exports_stable_schema(): void
    {
        $observation = new LifecycleSemanticObservation(
            LifecycleTransition::Terminal,
            LifecycleSemanticState::Provisional,
            OrderingIdentity::fromSourceAndValue('event-sequence', '12350'),
            'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            BoundedVarianceMarker::Provisional,
        );

        $this->assertTrue($observation->isTerminal());
        $this->assertSame([
            'transition' => 'terminal',
            'state' => 'provisional',
            'orderingIdentity' => ['source' => 'event-sequence', 'value' => '12350'],
            'subjectId' => 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            'variance' => 'provisional',
        ], $observation->toArray());
    }

    public function test_blank_lifecycle_subject_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LifecycleSemanticObservation(
            LifecycleTransition::Bridge,
            LifecycleSemanticState::Confirmed,
            OrderingIdentity::fromSourceAndValue('event-sequence', '12347'),
            '',
        );
    }
}
