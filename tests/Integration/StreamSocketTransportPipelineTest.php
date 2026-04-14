<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Events\RawEvent;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Internal\Transport\StreamSocketTransport;
use Apntalk\EslCore\Replies\ApiReply;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Tests\Fixtures\EslFixtureBuilder;
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

    public function test_internal_stream_socket_transport_handles_multiple_frames_in_single_read(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $transport = new StreamSocketTransport($this->transportSide);
        $pipeline = new InboundPipeline();
        $stream = EslFixtureBuilder::authAccepted()
            . EslFixtureBuilder::eventPlain(EslFixtureBuilder::eventData(['Event-Name' => 'UNEXPECTED_THING']))
            . EslFixtureBuilder::backgroundJobEvent();

        fwrite($this->peerSide, $stream);
        fclose($this->peerSide);
        $this->peerSide = null;

        $decoded = [];

        while (($chunk = $transport->read(4096)) !== null) {
            if ($chunk === '') {
                continue;
            }

            $pipeline->push($chunk);
            array_push($decoded, ...$pipeline->drain());
        }

        $pipeline->finish();

        $this->assertCount(3, $decoded);
        $this->assertSame(InboundMessageType::Reply, $decoded[0]->type());
        $this->assertInstanceOf(RawEvent::class, $decoded[1]->event());
        $this->assertInstanceOf(BackgroundJobEvent::class, $decoded[2]->event());
    }

    public function test_internal_stream_socket_transport_handles_delayed_body_arrival(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();
        stream_set_blocking($this->transportSide, false);

        $transport = new StreamSocketTransport($this->transportSide);
        $pipeline = new InboundPipeline();
        $frame = EslFixtureBuilder::apiResponse("+OK delayed body\n");
        $headerEnd = strpos($frame, "\n\n") + 2;

        fwrite($this->peerSide, substr($frame, 0, $headerEnd + 4));

        $pipeline->push($transport->read(4096) ?? '');
        $this->assertSame([], $pipeline->drain());

        fwrite($this->peerSide, substr($frame, $headerEnd + 4));
        fclose($this->peerSide);
        $this->peerSide = null;

        $decoded = $this->readAllDecoded($transport, $pipeline, 4096);
        $pipeline->finish();

        $this->assertCount(1, $decoded);
        $this->assertInstanceOf(ApiReply::class, $decoded[0]->reply());
        $this->assertSame("+OK delayed body\n", $decoded[0]->reply()?->body());
    }

    public function test_internal_stream_socket_transport_handles_bgapi_acceptance_then_later_completion(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();
        stream_set_blocking($this->transportSide, false);

        $transport = new StreamSocketTransport($this->transportSide);
        $pipeline = new InboundPipeline();

        fwrite($this->peerSide, EslFixtureBuilder::bgapiAccepted());

        $firstBatch = $this->readUntilIdle($transport, $pipeline);

        $this->assertCount(1, $firstBatch);
        $this->assertInstanceOf(BgapiAcceptedReply::class, $firstBatch[0]->reply());

        fwrite($this->peerSide, EslFixtureBuilder::backgroundJobEvent());
        fclose($this->peerSide);
        $this->peerSide = null;

        $secondBatch = $this->readAllDecoded($transport, $pipeline, 4096);
        $pipeline->finish();

        $this->assertCount(1, $secondBatch);
        $this->assertInstanceOf(BackgroundJobEvent::class, $secondBatch[0]->event());
        $this->assertSame(
            $firstBatch[0]->reply()?->jobUuid(),
            $secondBatch[0]->event()?->jobUuid()
        );
    }

    public function test_internal_stream_socket_transport_reports_mid_frame_connection_loss_cleanly(): void
    {
        [$this->transportSide, $this->peerSide] = $this->socketPair();

        $transport = new StreamSocketTransport($this->transportSide);
        $pipeline = new InboundPipeline();
        $frame = EslFixtureBuilder::backgroundJobEvent(jobResult: "+OK delayed body\n");

        fwrite($this->peerSide, substr($frame, 0, strlen($frame) - 5));
        fclose($this->peerSide);
        $this->peerSide = null;

        while (($chunk = $transport->read(19)) !== null) {
            if ($chunk === '') {
                continue;
            }

            $pipeline->push($chunk);
            $pipeline->drain();
        }

        $this->expectException(TruncatedFrameException::class);

        $pipeline->finish();
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

    /**
     * @return list<\Apntalk\EslCore\Inbound\DecodedInboundMessage>
     */
    private function readAllDecoded(
        StreamSocketTransport $transport,
        InboundPipeline $pipeline,
        int $readSize,
    ): array {
        $decoded = [];

        while (($chunk = $transport->read($readSize)) !== null) {
            if ($chunk === '') {
                continue;
            }

            $pipeline->push($chunk);
            array_push($decoded, ...$pipeline->drain());
        }

        return $decoded;
    }

    /**
     * @return list<\Apntalk\EslCore\Inbound\DecodedInboundMessage>
     */
    private function readUntilIdle(
        StreamSocketTransport $transport,
        InboundPipeline $pipeline,
    ): array {
        $decoded = [];

        while (true) {
            $chunk = $transport->read(4096);

            if ($chunk === null || $chunk === '') {
                break;
            }

            $pipeline->push($chunk);
            array_push($decoded, ...$pipeline->drain());
        }

        return $decoded;
    }
}
