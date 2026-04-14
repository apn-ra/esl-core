<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when serialization of an ESL command or message fails.
 *
 * This typically indicates a command was constructed with invalid
 * or unsupported parameters.
 */
class SerializationException extends ProtocolException {}
