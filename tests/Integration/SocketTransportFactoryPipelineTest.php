<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Integration;

use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Events\BackgroundJobEvent;
use Apntalk\EslCore\Inbound\InboundMessageType;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replies\BgapiAcceptedReply;
use Apntalk\EslCore\Tests\Fixtures\FixtureLoader;
use Apntalk\EslCore\Transport\SocketEndpoint;
use Apntalk\EslCore\Transport\SocketTransportFactory;
use PHPUnit\Framework\TestCase;

/**
 * Integration proof for the stable public transport-construction path composed
 * with the preferred inbound byte-ingress facade.
 *
 * This class uses only public transport and inbound types. Listener ownership,
 * long-lived read loops, reconnect policy, and broader runtime supervision
 * remain outside core and are intentionally not modeled here.
 */
final class SocketTransportFactoryPipelineTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    /** @var resource|null */
    private $peer = null;

    protected function tearDown(): void
    {
        if (is_resource($this->peer)) {
            fclose($this->peer);
        }

        if (is_resource($this->server)) {
            fclose($this->server);
        }
    }

    public function test_socket_transport_factory_connect_and_inbound_pipeline_prove_supported_public_path(): void
    {
        $this->server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        $this->assertNotFalse($this->server, sprintf(
            'Failed to create local socket server: [%d] %s',
            $errorCode,
            $errorMessage,
        ));

        $serverName = stream_socket_get_name($this->server, false);
        $this->assertIsString($serverName);
        [$host, $port] = explode(':', $serverName);

        $transport = (new SocketTransportFactory())->connect(
            SocketEndpoint::tcp($host, (int) $port, 1.0)
        );
        $pipeline = InboundPipeline::withDefaults();

        $this->peer = stream_socket_accept($this->server, 1.0);
        $this->assertNotFalse($this->peer);

        $transport->write((new AuthCommand('ClueCon'))->serialize());
        $this->assertSame("auth ClueCon\n\n", fread($this->peer, 4096));

        $fixture = FixtureLoader::load('sequences/bgapi-acceptance-and-completion.esl');

        foreach ([13, 21, strlen($fixture) - 34] as $length) {
            fwrite($this->peer, substr($fixture, 0, $length));
            $fixture = substr($fixture, $length);
        }

        if ($fixture !== '') {
            fwrite($this->peer, $fixture);
        }

        fclose($this->peer);
        $this->peer = null;

        $decoded = [];

        while (($chunk = $transport->read(17)) !== null) {
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
        $this->assertSame(
            $decoded[0]->reply()?->jobUuid(),
            $decoded[1]->event()?->jobUuid(),
        );

        $transport->close();
        $this->assertFalse($transport->isConnected());
    }
}
