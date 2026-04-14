<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Exceptions;

/**
 * Thrown when a reply does not match the expected reply type for a command.
 *
 * For example: sending a BgapiCommand and receiving an ErrorReply when the
 * caller explicitly asserted a BgapiAcceptedReply.
 */
class UnexpectedReplyException extends ProtocolException {}
