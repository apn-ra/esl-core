<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Exceptions\ParseException;
use Apntalk\EslCore\Exceptions\TruncatedFrameException;
use Apntalk\EslCore\Inbound\DecodedInboundMessage;

/**
 * Stable public facade for inbound ESL byte-stream decoding.
 *
 * Implementations accept raw inbound bytes, emit decoded protocol messages,
 * and hide the provisional parser/classifier pipeline from upper layers.
 */
interface InboundPipelineInterface
{
    /**
     * Feed raw inbound bytes into the decoder.
     *
     * @throws ParseException if the framed data is structurally malformed.
     */
    public function push(string $bytes): void;

    /**
     * Drain all decoded inbound messages produced since the last drain().
     *
     * If a frame in the drained batch fails during reply/event decoding, the
     * exception is thrown and no partial decoded-message list is returned. The
     * already drained parser frames are not replayed; callers should reset or
     * discard the pipeline before continuing after a parse failure.
     *
     * @throws ParseException if a drained frame cannot be decoded structurally.
     *
     * @return list<DecodedInboundMessage>
     */
    public function drain(): array;

    /**
     * Convenience helper for one-shot byte decoding.
     *
     * @throws ParseException if the framed data is structurally malformed.
     * @return list<DecodedInboundMessage>
     */
    public function decode(string $bytes): array;

    /**
     * Signal end-of-input and fail if an incomplete frame remains buffered.
     *
     * @throws TruncatedFrameException
     */
    public function finish(): void;

    /**
     * Reset the incremental decoder to its initial state.
     */
    public function reset(): void;

    /**
     * Return the current buffered byte count.
     */
    public function bufferedByteCount(): int;
}
