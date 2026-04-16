<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Replies;

use Apntalk\EslCore\Contracts\ReplyInterface;
use Apntalk\EslCore\Internal\Classification\ClassifiedInboundMessage;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;

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
    public function fromClassified(ClassifiedInboundMessage $classified): ReplyInterface
    {
        return match ($classified->category) {
            InboundMessageCategory::AuthAccepted   => AuthAcceptedReply::fromFrame($classified->frame),
            InboundMessageCategory::BgapiAccepted  => BgapiAcceptedReply::fromFrame($classified->frame),
            InboundMessageCategory::CommandAccepted => CommandReply::fromFrame($classified->frame),
            InboundMessageCategory::CommandError    => ErrorReply::fromFrame($classified->frame),
            InboundMessageCategory::ApiResponse    => ApiReply::fromFrame($classified->frame),
            default                                => UnknownReply::fromFrame($classified->frame),
        };
    }
}
