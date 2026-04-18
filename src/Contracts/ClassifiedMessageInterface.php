<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Contracts;

use Apntalk\EslCore\Protocol\Frame;

/**
 * Public read-only contract for a classified inbound ESL message.
 *
 * This contract exists for advanced callers that intentionally own lower-level
 * frame/classifier composition but should not need to type against the current
 * internal ClassifiedInboundMessage carrier directly.
 *
 * It does not replace InboundPipeline as the preferred ingress path.
 *
 * @api
 */
interface ClassifiedMessageInterface
{
    public function frame(): Frame;

    public function isAuthRequest(): bool;

    public function isAuthAccepted(): bool;

    public function isBgapiAccepted(): bool;

    public function isCommandAccepted(): bool;

    public function isCommandError(): bool;

    public function isApiResponse(): bool;

    public function isEvent(): bool;

    public function isDisconnectNotice(): bool;

    public function isUnknown(): bool;
}
