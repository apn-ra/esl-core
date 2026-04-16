<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

use Apntalk\EslCore\Contracts\EventFactoryInterface;
use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Contracts\InboundPipelineInterface;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\EventParser;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replies\UnknownReply;

/**
 * Stable public facade for decoding inbound ESL bytes into typed messages.
 *
 * This facade composes the current parser, classifier, reply factory, and
 * event factory internally so upper layers can depend on one supported
 * ingress surface instead of provisional concrete internals. Prefer
 * InboundPipeline::withDefaults() for the stable default construction path;
 * direct constructor injection remains an advanced public composition seam.
 *
 * @api
 */
final class InboundPipeline implements InboundPipelineInterface
{
    private readonly FrameParser $frameParser;
    private readonly InboundMessageClassifier $classifier;
    private readonly EventParserInterface $eventParser;
    private readonly EventFactoryInterface $eventFactory;
    private readonly ReplyFactory $replyFactory;

    /**
     * Stable public default-construction path for the supported ingress facade.
     *
     * Prefer this named constructor when you want the standard fixture-backed
     * parser/classifier/event/reply pipeline without coupling to collaborator
     * implementation details.
     */
    public static function withDefaults(): self
    {
        return new self();
    }

    /**
     * Advanced composition path for callers intentionally overriding the
     * current parser/classifier/event/reply collaborators.
     *
     * For the stable public ingress construction path, prefer withDefaults().
     * This constructor remains public for lower-level fixture-backed
     * composition, but its collaborator types are more concrete and more
     * provisional than the default facade path before 1.0.0.
     */
    public function __construct(
        ?FrameParser $frameParser = null,
        ?InboundMessageClassifier $classifier = null,
        ?EventParserInterface $eventParser = null,
        ?EventFactoryInterface $eventFactory = null,
        ?ReplyFactory $replyFactory = null,
    ) {
        $this->frameParser = $frameParser ?? new FrameParser();
        $this->classifier = $classifier ?? new InboundMessageClassifier();
        $this->eventParser = $eventParser ?? new EventParser();
        $this->eventFactory = $eventFactory ?? new EventFactory($this->eventParser);
        $this->replyFactory = $replyFactory ?? new ReplyFactory();
    }

    public function push(string $bytes): void
    {
        $this->frameParser->feed($bytes);
    }

    public function drain(): array
    {
        $decoded = [];

        foreach ($this->frameParser->drain() as $frame) {
            $classified = $this->classifier->classify($frame);

            $decoded[] = match ($classified->category) {
                InboundMessageCategory::ServerAuthRequest => DecodedInboundMessage::forServerAuthRequest(),
                InboundMessageCategory::DisconnectNotice => DecodedInboundMessage::forDisconnectNotice(),
                InboundMessageCategory::EventMessage => $this->decodeEventFrame($frame),
                InboundMessageCategory::Unknown => DecodedInboundMessage::forUnknown(UnknownReply::fromFrame($frame)),
                default => DecodedInboundMessage::forReply($this->replyFactory->fromClassified($classified)),
            };
        }

        return $decoded;
    }

    public function decode(string $bytes): array
    {
        $this->push($bytes);

        return $this->drain();
    }

    public function finish(): void
    {
        $this->frameParser->finish();
    }

    public function reset(): void
    {
        $this->frameParser->reset();
    }

    public function bufferedByteCount(): int
    {
        return $this->frameParser->bufferedByteCount();
    }

    private function decodeEventFrame(\Apntalk\EslCore\Protocol\Frame $frame): DecodedInboundMessage
    {
        $normalized = $this->eventParser->parse($frame);
        $event = $this->eventFactory->fromNormalized($normalized);

        return DecodedInboundMessage::forEvent($normalized, $event);
    }
}
