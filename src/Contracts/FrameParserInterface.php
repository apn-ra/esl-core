<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Contract for incremental ESL frame parsers.
 *
 * Implementations receive raw bytes via feed() and produce complete
 * Frame objects that can be drained from an internal buffer.
 *
 * The parser must be safe for partial reads: bytes may arrive in any
 * chunk sizes and may be split across header boundaries or body boundaries.
 *
 * The parser must be transport-neutral: it does not own I/O, loops, or
 * scheduling. Callers feed bytes and drain frames.
 *
 * Upper-layer integrations should prefer `InboundPipelineInterface` for
 * supported inbound decoding. This lower-level contract remains useful for
 * internal composition, targeted tests, and narrow advanced integrations.
 */
interface FrameParserInterface
{
    /**
     * Feed raw bytes to the parser.
     *
     * Bytes are appended to the internal buffer and processing occurs
     * immediately. Any complete frames are held until drain() is called.
     *
     * @throws ParseException if a malformed header block is detected.
     *                        After a ParseException, the parser state is
     *                        undefined; callers should call reset() or
     *                        discard this parser instance.
     */
    public function feed(string $bytes): void;

    /**
     * Drain all complete frames produced since the last drain() call.
     *
     * Returns an empty array if no complete frames are available.
     * Resets the internal completed-frames buffer to empty.
     *
     * @return list<Frame>
     */
    public function drain(): array;

    /**
     * Reset the parser to its initial state, discarding any buffered data.
     */
    public function reset(): void;

    /**
     * How many bytes are currently buffered (not yet emitted as frames).
     */
    public function bufferedByteCount(): int;
}
