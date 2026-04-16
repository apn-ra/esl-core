<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Tests\Contract\Transport;

use Apntalk\EslCore\Contracts\TransportFactoryInterface;
use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Transport\SocketEndpoint;
use Apntalk\EslCore\Transport\SocketTransportFactory;
use PHPUnit\Framework\TestCase;

final class SocketTransportFactoryTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    /** @var resource|null */
    private $acceptedPeer = null;

    /** @var resource|null */
    private $socketPairPeer = null;

    private TransportFactoryInterface $factory;

    protected function setUp(): void
    {
        $this->factory = new SocketTransportFactory();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->acceptedPeer)) {
            fclose($this->acceptedPeer);
        }

        if (is_resource($this->server)) {
            fclose($this->server);
        }

        if (is_resource($this->socketPairPeer)) {
            fclose($this->socketPairPeer);
        }
    }

    public function test_connect_returns_transport_interface_for_socket_endpoint(): void
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

        $transport = $this->factory->connect(SocketEndpoint::tcp($host, (int) $port, 1.0));
        $this->acceptedPeer = stream_socket_accept($this->server, 1.0);

        $this->assertInstanceOf(TransportInterface::class, $transport);
        $this->assertNotFalse($this->acceptedPeer);

        $transport->write("auth ClueCon\n\n");
        $received = fread($this->acceptedPeer, 4096);

        $this->assertSame("auth ClueCon\n\n", $received);

        fwrite($this->acceptedPeer, "Content-Type: auth/request\n\n");

        $this->assertSame("Content-Type: auth/request\n\n", $transport->read(4096));

        $transport->close();
        $this->assertFalse($transport->isConnected());
    }

    public function test_from_stream_wraps_existing_connected_stream_without_internal_class_dependency(): void
    {
        [$transportSide, $this->socketPairPeer] = $this->socketPair();

        $transport = $this->factory->fromStream($transportSide);

        $this->assertInstanceOf(TransportInterface::class, $transport);

        fwrite($this->socketPairPeer, "Content-Type: auth/request\n\n");

        $this->assertSame("Content-Type: auth/request\n\n", $transport->read(4096));

        $transport->write("auth ClueCon\n\n");

        $this->assertSame("auth ClueCon\n\n", fread($this->socketPairPeer, 4096));

        $transport->close();
        $this->assertFalse($transport->isConnected());
    }

    public function test_connect_throws_transport_exception_when_connection_cannot_be_opened(): void
    {
        $this->expectException(TransportException::class);

        $this->factory->connect(new SocketEndpoint('tcp://127.0.0.1:1', 0.05));
    }

    public function test_socket_endpoint_preserves_minimal_public_connection_inputs(): void
    {
        $endpoint = new SocketEndpoint(
            'tcp://127.0.0.1:8021',
            5.5,
            ['socket' => ['so_reuseport' => true]],
            STREAM_CLIENT_CONNECT,
        );

        $this->assertSame('tcp://127.0.0.1:8021', $endpoint->address());
        $this->assertSame(5.5, $endpoint->timeoutSeconds());
        $this->assertSame(['socket' => ['so_reuseport' => true]], $endpoint->contextOptions());
        $this->assertSame(STREAM_CLIENT_CONNECT, $endpoint->flags());
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
