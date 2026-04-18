<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Unit\Vocabulary;

use Apntalk\EslCore\Vocabulary\BoundedVarianceMarker;
use Apntalk\EslCore\Vocabulary\CorpusRowIdentity;
use Apntalk\EslCore\Vocabulary\FinalityMarker;
use Apntalk\EslCore\Vocabulary\OrderingIdentity;
use Apntalk\EslCore\Vocabulary\PublicationId;
use Apntalk\EslCore\Vocabulary\PublicationSource;
use Apntalk\EslCore\Vocabulary\TerminalCause;
use Apntalk\EslCore\Vocabulary\TerminalPublication;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TerminalPublicationTest extends TestCase
{
    public function test_terminal_publication_exports_stable_schema(): void
    {
        $publication = new TerminalPublication(
            PublicationId::fromString('terminal-pub-1'),
            FinalityMarker::Final,
            TerminalCause::Hangup,
            PublicationSource::ProtocolEvent,
            1_482_422_205_000_000,
            OrderingIdentity::fromSourceAndValue('event-sequence', '12350'),
            CorpusRowIdentity::fromCorpusAndRow('live-events', 'channel-hangup'),
            BoundedVarianceMarker::BoundedVariance,
        );

        $this->assertTrue($publication->isFinal());
        $this->assertSame([
            'publicationId' => 'terminal-pub-1',
            'finality' => 'final',
            'terminalCause' => 'hangup',
            'source' => 'protocol-event',
            'publishedAtMicros' => 1_482_422_205_000_000,
            'orderingIdentity' => ['source' => 'event-sequence', 'value' => '12350'],
            'corpusRowIdentity' => ['corpus' => 'live-events', 'row' => 'channel-hangup'],
            'variance' => 'bounded-variance',
        ], $publication->toArray());
    }

    public function test_terminal_publication_rejects_non_positive_publication_timestamp(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TerminalPublication(
            PublicationId::fromString('terminal-pub-1'),
            FinalityMarker::Final,
            TerminalCause::Hangup,
            PublicationSource::ProtocolEvent,
            0,
            OrderingIdentity::fromSourceAndValue('event-sequence', '12350'),
        );
    }
}
