<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

use RuntimeException;

/**
 * Thrown when a transport-layer operation fails.
 *
 * This is intentionally separate from ProtocolException so consumers can
 * distinguish network/socket failures from protocol-level failures.
 */
class TransportException extends RuntimeException {}
