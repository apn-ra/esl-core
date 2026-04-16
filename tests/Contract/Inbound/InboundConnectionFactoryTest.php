<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Inbound;

use Apntalk\EslCore\Contracts\InboundConnectionFactoryInterface;
use Apntalk\EslCore\Contracts\InboundPipelineInterface;
use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Inbound\InboundConnectionFactory;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
use PHPUnit\Framework\TestCase;

final class InboundConnectionFactoryTest extends TestCase
{
    /** @var resource|null */
    private $transportSide = null;

    /** @var resource|null */
    private $peerSide = null;

    private InboundConnectionFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = new InboundConnectionFactory();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->transportSide)) {
            fclose($this->transportSide);
        }

        if (is_resource($this->peerSide)) {
            fclose($this->peerSide);
        }
    }

    public function test_prepare_accepted_stream_returns_supported_public_bootstrap_bundle(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $sessionId = ConnectionSessionId::fromString('abababab-abab-4aba-8aba-abababababab');
        $prepared = $this->factory->prepareAcceptedStream($this->transportSide, $sessionId);

        $this->assertInstanceOf(TransportInterface::class, $prepared->transport());
        $this->assertInstanceOf(InboundPipelineInterface::class, $prepared->pipeline());
        $this->assertTrue($prepared->sessionId()->equals($sessionId));
        $this->assertTrue($prepared->correlationContext()->sessionId()->equals($sessionId));
    }

    public function test_prepare_accepted_stream_generates_session_id_when_not_supplied(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $prepared = $this->factory->prepareAcceptedStream($this->transportSide);

        $this->assertNotSame('', $prepared->sessionId()->toString());
        $this->assertTrue($prepared->correlationContext()->sessionId()->equals($prepared->sessionId()));
    }

    public function test_prepared_connection_reads_decodes_and_assigns_metadata_without_ad_hoc_assembly(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $sessionId = ConnectionSessionId::fromString('cdcdcdcd-cdcd-4cdc-8cdc-cdcdcdcdcdcd');
        $prepared = $this->factory->prepareAcceptedStream($this->transportSide, $sessionId);

        fwrite(
            $this->peerSide,
            EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::channelCreateEvent('a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78')
        );
        fclose($this->peerSide);
        $this->peerSide = null;

        $decoded = [];

        while (($chunk = $prepared->transport()->read(4096)) !== null) {
            if ($chunk === '') {
                continue;
            }

            $prepared->pipeline()->push($chunk);
            array_push($decoded, ...$prepared->pipeline()->drain());
        }

        $prepared->pipeline()->finish();

        $this->assertCount(2, $decoded);
        $this->assertSame(InboundMessageType::Reply, $decoded[0]->type());
        $this->assertSame(InboundMessageType::Event, $decoded[1]->type());

        $replyEnvelope = new ReplyEnvelope(
            $decoded[0]->reply(),
            $prepared->correlationContext()->nextMetadataForReply($decoded[0]->reply()),
        );
        $eventEnvelope = new EventEnvelope(
            $decoded[1]->event(),
            $prepared->correlationContext()->nextMetadataForEvent($decoded[1]->event()),
        );

        $this->assertTrue($replyEnvelope->sessionId()->equals($sessionId));
        $this->assertTrue($eventEnvelope->sessionId()->equals($sessionId));
        $this->assertSame(1, $replyEnvelope->observationSequence()->position());
        $this->assertSame(2, $eventEnvelope->observationSequence()->position());
        $this->assertSame('a3ebbd02-f43a-4d2e-a7f5-a2a2d87f4e78', $eventEnvelope->channelCorrelation()?->uniqueId());
    }

    /**
     * @return array{resource, resource}
     */
    private function socketPair(): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);

        $this->assertIsArray($pair);
        $this->assertCount(2, $pair);

        stream_set_blocking($pair[0], true);
        stream_set_blocking($pair[1], true);

        return [$pair[0], $pair[1]];
    }
}
