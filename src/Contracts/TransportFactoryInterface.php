<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Exceptions\TransportException;
use Apntalk\EslCore\Transport\SocketEndpoint;

/**
 * Stable public seam for constructing byte-oriented transports.
 *
 * Implementations may connect to an endpoint or wrap an already accepted
 * stream resource, but they must not add reconnect, supervision, or loop
 * ownership. Those concerns remain in upper-layer packages.
 */
interface TransportFactoryInterface
{
    /**
     * Connect to the given endpoint and return a byte-oriented transport.
     *
     * @throws TransportException on connection failure.
     */
    public function connect(SocketEndpoint $endpoint): TransportInterface;

    /**
     * Wrap an already connected PHP stream resource as a transport.
     *
     * @param resource $stream
     *
     * @throws TransportException when the stream is invalid.
     */
    public function fromStream($stream): TransportInterface;
}
