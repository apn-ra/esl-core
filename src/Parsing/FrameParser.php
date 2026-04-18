<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Parsing;

use Apntalk\EslCore\Contracts\CompletableFrameParserInterface;
use Apntalk\EslCore\Exceptions\MalformedFrameException;
use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Internal\ParserState;
use Apntalk\EslCore\Protocol\Frame;
use Apntalk\EslCore\Protocol\HeaderBag;

/**
 * Incremental, stateful ESL frame parser.
 *
 * Parses raw bytes into Frame objects. Handles:
 * - Partial reads (bytes arriving in any chunk sizes)
 * - Multiple frames in a single feed
 * - Frames without bodies (auth/request, command/reply without Content-Length)
 * - Frames with bodies (api/response, text/event-plain)
 * - Large bodies that span multiple feed() calls
 *
 * ESL framing rules:
 *   1. Headers are separated from the body by \n\n.
 *   2. Each header is a "Key: Value\n" line.
 *   3. If Content-Length: N is present, the next N bytes are the body.
 *   4. If Content-Length is absent, the body is empty.
 *
 * The parser is transport-neutral: it does not own I/O, loops, or scheduling.
 * It also does not impose a maximum Content-Length/body-size cap; callers that
 * need memory bounds or hostile-peer protection must enforce those outside the
 * parser before or around feed().
 *
 * @see FrameParserInterface
 */
final class FrameParser implements CompletableFrameParserInterface
{
    /** Raw byte buffer. */
    private string $buffer = '';

    private ParserState $state = ParserState::AwaitingHeaders;

    private ?HeaderBag $pendingHeaders = null;

    /** Number of body bytes remaining for the current frame. */
    private int $pendingBodyLength = 0;

    /** @var list<Frame> */
    private array $completed = [];

    /**
     * Feed raw bytes to the parser.
     *
     * @throws ParseException if a malformed header block is encountered.
     *
     * Large digit-only Content-Length values are accepted as protocol input and
     * will keep the parser in a buffered waiting state until enough bytes arrive
     * or finish() reports truncation. This method does not apply a body-size cap.
     */
    public function feed(string $bytes): void
    {
        $this->buffer .= $bytes;
        $this->tick();
    }

    /**
     * Drain all completed frames since the last drain() call.
     *
     * @return list<Frame>
     */
    public function drain(): array
    {
        $frames          = $this->completed;
        $this->completed = [];
        return $frames;
    }

    public function reset(): void
    {
        $this->buffer            = '';
        $this->state             = ParserState::AwaitingHeaders;
        $this->pendingHeaders    = null;
        $this->pendingBodyLength = 0;
        $this->completed         = [];
    }

    public function bufferedByteCount(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Signal that no more bytes will arrive for the current parse attempt.
     *
     * @throws TruncatedFrameException if an incomplete frame is still buffered.
     */
    public function finish(): void
    {
        if ($this->state === ParserState::ReadingBody) {
            throw new TruncatedFrameException(
                sprintf(
                    'End of input reached with %d/%d body bytes buffered',
                    strlen($this->buffer),
                    $this->pendingBodyLength
                )
            );
        }

        if ($this->buffer !== '') {
            throw new TruncatedFrameException(
                sprintf(
                    'End of input reached with %d buffered header bytes',
                    strlen($this->buffer)
                )
            );
        }
    }

    /**
     * Drive the state machine until no more progress can be made.
     *
     * @throws ParseException
     */
    private function tick(): void
    {
        while (true) {
            if ($this->state === ParserState::AwaitingHeaders) {
                if (!$this->tryParseHeaders()) {
                    break;
                }
            } else {
                // ReadingBody
                if (!$this->tryReadBody()) {
                    break;
                }
            }
        }
    }

    /**
     * Attempt to parse the next header block from the buffer.
     *
     * Returns true if headers were found and state advanced (may loop again).
     * Returns false if more data is needed.
     *
     * @throws ParseException
     */
    private function tryParseHeaders(): bool
    {
        $delimPos = strpos($this->buffer, "\n\n");

        if ($delimPos === false) {
            return false; // Need more data
        }

        $headerBlock    = substr($this->buffer, 0, $delimPos);
        $this->buffer   = substr($this->buffer, $delimPos + 2);

        // HeaderBag::fromHeaderBlock can throw ParseException on malformed input
        $this->pendingHeaders = HeaderBag::fromHeaderBlock($headerBlock);

        $contentLengthValue = $this->pendingHeaders->get('Content-Length');

        if ($contentLengthValue !== null) {
            if (!ctype_digit($contentLengthValue)) {
                throw new MalformedFrameException(
                    "Invalid Content-Length value: {$contentLengthValue}"
                );
            }
            $this->pendingBodyLength = (int) $contentLengthValue;
        } else {
            $this->pendingBodyLength = 0;
        }

        if ($this->pendingBodyLength > 0) {
            $this->state = ParserState::ReadingBody;
        } else {
            // No body — emit immediately and loop
            $this->completed[]    = new Frame($this->pendingHeaders, '');
            $this->pendingHeaders = null;
            // Stay in AwaitingHeaders, loop again
        }

        return true;
    }

    /**
     * Attempt to read a pending body from the buffer.
     *
     * Returns true if the body was completed and state reset (may loop again).
     * Returns false if more data is needed.
     */
    private function tryReadBody(): bool
    {
        if (strlen($this->buffer) < $this->pendingBodyLength) {
            return false; // Need more data
        }

        $body           = substr($this->buffer, 0, $this->pendingBodyLength);
        $this->buffer   = substr($this->buffer, $this->pendingBodyLength);

        // pendingHeaders is always set when in ReadingBody state
        /** @var HeaderBag $headers */
        $headers = $this->pendingHeaders;

        $this->completed[]       = new Frame($headers, $body);
        $this->pendingHeaders    = null;
        $this->pendingBodyLength = 0;
        $this->state             = ParserState::AwaitingHeaders;

        return true;
    }
}
