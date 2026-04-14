<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when ESL parsing fails due to malformed, truncated, or unsupported input.
 *
 * Prefer catching the more specific subtypes when you need to distinguish:
 * - MalformedFrameException
 * - TruncatedFrameException
 * - UnsupportedContentTypeException
 *
 * Callers catching this exception should consider the connection state
 * to be potentially corrupt and may need to reset the parser or
 * close the connection.
 */
class ParseException extends ProtocolException {}
