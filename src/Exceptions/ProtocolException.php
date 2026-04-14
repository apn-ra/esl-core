<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

use RuntimeException;

/**
 * Base exception for all ESL protocol-layer failures.
 *
 * Consumers may catch this type to handle all protocol-level errors as a group.
 * More specific subtypes are provided for finer-grained handling.
 */
class ProtocolException extends RuntimeException {}
