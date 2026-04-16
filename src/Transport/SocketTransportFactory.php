<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Transport;

use Apntalk\EslCore\Contracts\TransportFactoryInterface;
use Apntalk\EslCore\Contracts\TransportInterface;
use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Internal\Transport\StreamSocketTransport;

/**
 * Public socket/stream transport construction seam for upper-layer packages.
 *
 * The returned transport remains byte-oriented only. Connection lifecycle
 * policy beyond initial construction remains outside core.
 *
 * @api
 */
final class SocketTransportFactory implements TransportFactoryInterface
{
    public function connect(SocketEndpoint $endpoint): TransportInterface
    {
        $context = $endpoint->contextOptions() === []
            ? null
            : stream_context_create($endpoint->contextOptions());

        $errorCode = 0;
        $errorMessage = '';

        $stream = @stream_socket_client(
            $endpoint->address(),
            $errorCode,
            $errorMessage,
            $endpoint->timeoutSeconds(),
            $endpoint->flags(),
            $context,
        );

        if (!is_resource($stream)) {
            $message = $errorMessage !== ''
                ? $errorMessage
                : 'stream_socket_client returned a non-resource result.';

            throw new TransportException(sprintf(
                'Failed to connect transport to "%s": [%d] %s',
                $endpoint->address(),
                $errorCode,
                $message,
            ));
        }

        return $this->fromStream($stream);
    }

    public function fromStream($stream): TransportInterface
    {
        return new StreamSocketTransport($stream);
    }
}
