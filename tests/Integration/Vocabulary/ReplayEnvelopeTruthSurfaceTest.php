<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration\Vocabulary;

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replay\ReplayEnvelope;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use Apntalk\EslCore\Vocabulary\BoundedVarianceMarker;
use Apntalk\EslCore\Vocabulary\FinalityMarker;
use Apntalk\EslCore\Vocabulary\OrderingIdentity;
use Apntalk\EslCore\Vocabulary\PublicationId;
use Apntalk\EslCore\Vocabulary\PublicationSource;
use Apntalk\EslCore\Vocabulary\TerminalCause;
use Apntalk\EslCore\Vocabulary\TerminalPublication;
use PHPUnit\Framework\TestCase;

final class ReplayEnvelopeTruthSurfaceTest extends TestCase
{
    public function test_replay_envelope_truth_surfaces_support_terminal_publication_schema(): void
    {
        $sessionId = ConnectionSessionId::fromString('77777777-7777-4777-8777-777777777777');
        $pipeline = InboundPipeline::withDefaults();
        $context = new CorrelationContext($sessionId);
        $factory = ReplayEnvelopeFactory::withSession($sessionId);

        $message = $pipeline->decode(EslFixtureBuilder::hangupEvent(
            uniqueId: 'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            hangupCause: 'NORMAL_CLEARING',
        ))[0];
        $metadata = $context->nextMetadataForEvent($message->event());
        $envelope = $factory->fromEventEnvelope(new EventEnvelope($message->event(), $metadata));

        $publication = new TerminalPublication(
            PublicationId::fromString('terminal-' . $envelope->identityFacts()['unique-id']),
            FinalityMarker::Final,
            TerminalCause::Hangup,
            PublicationSource::ReplayEnvelope,
            $envelope->capturedAtMicros(),
            OrderingIdentity::fromSourceAndValue('protocol-sequence', $envelope->orderingFacts()['protocol-sequence']),
            null,
            BoundedVarianceMarker::None,
        );

        $this->assertSame(ReplayEnvelope::SCHEMA_VERSION, $envelope->schemaVersion());
        $this->assertSame('CHANNEL_HANGUP', $envelope->identityFacts()['event-name']);
        $this->assertSame('12350', $envelope->orderingFacts()['protocol-sequence']);
        $this->assertSame(
            'a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78',
            $envelope->causalMetadata()['channel-correlation.unique-id'],
        );
        $this->assertSame('terminal-a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78', $publication->publicationId()->toString());
        $this->assertTrue($publication->isFinal());
    }
}
