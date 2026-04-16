<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ClassifiedMessageInterface;
use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Protocol\Frame;

/**
 * Produces typed ReplyInterface instances from classified inbound messages.
 *
 * This is the bridge between the classification layer and the typed reply layer.
 * Event messages are NOT handled here; pass them to EventParser instead.
 *
 * This class remains public for callers that intentionally own lower-level
 * frame/classifier composition, but it is not the preferred raw-byte ingress
 * path for upper layers. For the stable default decode seam, prefer
 * InboundPipeline::withDefaults().
 *
 * @api
 */
final class ReplyFactory
{
    /**
     * Produce a typed reply directly from a frame.
     *
     * This additive helper exists for advanced callers that already own a
     * parsed Frame but do not want to couple directly to the internal
     * ClassifiedInboundMessage carrier just to reach the typed reply layer.
     *
     * For the supported upper-layer ingress path, prefer
     * InboundPipeline::withDefaults().
     */
    public function fromFrame(
        Frame $frame,
        ?InboundMessageClassifierInterface $classifier = null,
    ): ReplyInterface {
        $classifier ??= new InboundMessageClassifier();

        return $this->fromClassification($classifier->classify($frame));
    }

    /**
     * Produce a typed reply from the public classified-message contract.
     *
     * This additive entrypoint lets advanced callers classify a frame once and
     * then type against a public contract instead of the current internal
     * ClassifiedInboundMessage carrier directly.
     */
    public function fromClassification(ClassifiedMessageInterface $classified): ReplyInterface
    {
        return match (true) {
            $classified->isAuthAccepted() => AuthAcceptedReply::fromFrame($classified->frame()),
            $classified->isBgapiAccepted() => BgapiAcceptedReply::fromFrame($classified->frame()),
            $classified->isCommandAccepted() => CommandReply::fromFrame($classified->frame()),
            $classified->isCommandError() => ErrorReply::fromFrame($classified->frame()),
            $classified->isApiResponse() => ApiReply::fromFrame($classified->frame()),
            default => UnknownReply::fromFrame($classified->frame()),
        };
    }

    public function fromClassified(ClassifiedInboundMessage $classified): ReplyInterface
    {
        return $this->fromClassification($classified);
    }
}
