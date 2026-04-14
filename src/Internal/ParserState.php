<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Internal;

/**
 * Internal state machine states for FrameParser.
 *
 * @internal Not part of the public API.
 */
enum ParserState
{
    /** Buffering bytes; looking for the \n\n header terminator. */
    case AwaitingHeaders;

    /** Headers parsed; buffering body bytes until Content-Length is satisfied. */
    case ReadingBody;
}
