<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when message classification fails unexpectedly.
 *
 * Classification should degrade gracefully to Unknown for unrecognized
 * message types. This exception is reserved for cases where the
 * classification invariants are violated (e.g., a null content-type
 * in a context where one is required).
 */
class ClassificationException extends ProtocolException {}
