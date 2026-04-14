<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Internal\Transport\StreamSocketTransport;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use PHPUnit\Framework\TestCase;

final class StreamSocketTransportPipelineTest extends TestCase
{
    /** @var resource|null */
    private $transportSide = null;

    /** @var resource|null */
    private $peerSide = null;

    protected function tearDown(): void
    {
        if (is_resource($this->transportSide)) {
            fclose($this->transportSide);
        }

        if (is_resource($this->peerSide)) {
            fclose($this->peerSide);
        }
    }

    public function test_internal_stream_socket_transport_handles_fragmented_real_stream_reads(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $transport = new StreamSocketTransport($this->transportSide);
        $pipeline = new InboundPipeline();
        $fixture = FixtureLoader::load('sequences/bgapi-acceptance-and-completion.esl');

        foreach ([17, 29, strlen($fixture) - 46] as $length) {
            fwrite($this->peerSide, substr($fixture, 0, $length));
            $fixture = substr($fixture, $length);
        }

        if ($fixture !== '') {
            fwrite($this->peerSide, $fixture);
        }

        fclose($this->peerSide);
        $this->peerSide = null;

        $decoded = [];

        while (($chunk = $transport->read(23)) !== null) {
            if ($chunk === '') {
                continue;
            }

            $pipeline->push($chunk);
            array_push($decoded, ...$pipeline->drain());
        }

        $pipeline->finish();

        $this->assertCount(2, $decoded);
        $this->assertSame(InboundMessageType::Reply, $decoded[0]->type());
        $this->assertSame(InboundMessageType::Event, $decoded[1]->type());
        $this->assertInstanceOf(BgapiAcceptedReply::class, $decoded[0]->reply());
        $this->assertInstanceOf(BackgroundJobEvent::class, $decoded[1]->event());
    }

    public function test_internal_stream_socket_transport_writes_full_command_payload(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $transport = new StreamSocketTransport($this->transportSide);
        $command = (new AuthCommand('ClueCon'))->serialize();

        $transport->write($command);

        $received = fread($this->peerSide, 4096);

        $this->assertSame($command, $received);
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
