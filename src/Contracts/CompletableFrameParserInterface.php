<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Exceptions\TruncatedFrameException;

/**
 * Advanced frame-parser contract for incremental parsers that can be finalized.
 *
 * `InboundPipeline` needs this narrower extension of `FrameParserInterface`
 * because the public pipeline exposes `finish()` as part of its end-of-input
 * contract. Upper-layer byte ingestion should still prefer
 * `InboundPipeline::withDefaults()`.
 *
 * @api
 */
interface CompletableFrameParserInterface extends FrameParserInterface
{
    /**
     * Signal that no more bytes will arrive for the current parse attempt.
     *
     * @throws TruncatedFrameException if an incomplete frame remains buffered.
     */
    public function finish(): void;
}
