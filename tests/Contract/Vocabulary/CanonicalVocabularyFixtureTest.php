<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Vocabulary;

use Apntalk\EslCore\Vocabulary\BoundedVarianceMarker;
use Apntalk\EslCore\Vocabulary\CorpusRowIdentity;
use Apntalk\EslCore\Vocabulary\DrainPosture;
use Apntalk\EslCore\Vocabulary\FinalityMarker;
use Apntalk\EslCore\Vocabulary\InFlightOperationId;
use Apntalk\EslCore\Vocabulary\LifecycleSemanticObservation;
use Apntalk\EslCore\Vocabulary\LifecycleSemanticState;
use Apntalk\EslCore\Vocabulary\LifecycleTransition;
use Apntalk\EslCore\Vocabulary\OrderingIdentity;
use Apntalk\EslCore\Vocabulary\PublicationId;
use Apntalk\EslCore\Vocabulary\PublicationSource;
use Apntalk\EslCore\Vocabulary\QueueState;
use Apntalk\EslCore\Vocabulary\ReconstructionPosture;
use Apntalk\EslCore\Vocabulary\RecoveryGenerationId;
use Apntalk\EslCore\Vocabulary\ReplayContinuity;
use Apntalk\EslCore\Vocabulary\RetryAttempt;
use Apntalk\EslCore\Vocabulary\RetryPosture;
use Apntalk\EslCore\Vocabulary\TerminalCause;
use Apntalk\EslCore\Vocabulary\TerminalPublication;
use PHPUnit\Framework\TestCase;

final class CanonicalVocabularyFixtureTest extends TestCase
{
    public function test_queue_retry_drain_fixture_maps_to_public_vocabulary(): void
    {
        $fixture = self::fixture('queue-retry-drain.json');
        $operationId = InFlightOperationId::fromString($fixture['operationId']);
        $attempt = new RetryAttempt(
            $operationId,
            $fixture['retry']['attempt'],
            $fixture['retry']['maxAttempts'],
            RetryPosture::from($fixture['retry']['posture']),
        );

        $this->assertSame(QueueState::InFlight, QueueState::from($fixture['queueState']));
        $this->assertSame(DrainPosture::Draining, DrainPosture::from($fixture['drainPosture']));
        $this->assertSame('generation-4', RecoveryGenerationId::fromString($fixture['recoveryGenerationId'])->toString());
        $this->assertSame(ReplayContinuity::Continuous, ReplayContinuity::from($fixture['replayContinuity']));
        $this->assertSame(ReconstructionPosture::HookRequired, ReconstructionPosture::from($fixture['reconstructionPosture']));
        $this->assertSame(BoundedVarianceMarker::None, BoundedVarianceMarker::from($fixture['variance']));
        $this->assertSame('op-bgapi-7f4db0f2', $attempt->operationId()->toString());
    }

    public function test_terminal_publication_fixture_maps_to_public_schema(): void
    {
        $fixture = self::fixture('terminal-publication.json');
        $publication = new TerminalPublication(
            PublicationId::fromString($fixture['publicationId']),
            FinalityMarker::from($fixture['finality']),
            TerminalCause::from($fixture['terminalCause']),
            PublicationSource::from($fixture['source']),
            $fixture['publishedAtMicros'],
            OrderingIdentity::fromSourceAndValue(
                $fixture['orderingIdentity']['source'],
                $fixture['orderingIdentity']['value'],
            ),
            CorpusRowIdentity::fromCorpusAndRow(
                $fixture['corpusRowIdentity']['corpus'],
                $fixture['corpusRowIdentity']['row'],
            ),
            BoundedVarianceMarker::from($fixture['variance']),
        );

        $this->assertSame($fixture, $publication->toArray());
    }

    public function test_lifecycle_semantic_fixture_maps_to_public_schema(): void
    {
        $fixture = self::fixture('lifecycle-semantics.json');
        $observations = [];

        foreach ($fixture['observations'] as $row) {
            $observations[] = new LifecycleSemanticObservation(
                LifecycleTransition::from($row['transition']),
                LifecycleSemanticState::from($row['state']),
                OrderingIdentity::fromSourceAndValue(
                    $row['orderingIdentity']['source'],
                    $row['orderingIdentity']['value'],
                ),
                $row['subjectId'],
                BoundedVarianceMarker::from($row['variance']),
            );
        }

        $this->assertSame(
            $fixture['observations'],
            array_map(
                static fn(LifecycleSemanticObservation $observation): array => $observation->toArray(),
                $observations,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function fixture(string $name): array
    {
        $json = file_get_contents(__DIR__ . '/../../Fixtures/vocabulary/' . $name);
        self::assertIsString($json);

        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
