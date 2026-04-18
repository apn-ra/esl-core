<?php

declare(strict_types=1);

namespace Apntalk\EslCore\Inbound;

use Apntalk\EslCore\Contracts\CompletableFrameParserInterface;
use Apntalk\EslCore\Contracts\EventFactoryInterface;
use Apntalk\EslCore\Contracts\EventParserInterface;
use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Contracts\InboundPipelineInterface;
use Apntalk\EslCore\Events\EventFactory;
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
    private readonly CompletableFrameParserInterface $frameParser;
    private readonly InboundMessageClassifierInterface $classifier;
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
     * Advanced contract-based composition path.
     *
     * This factory lets downstream packages provide public parser/classifier
     * contract implementations without depending on current concrete internals.
     * It remains an advanced path; the preferred ingress seam is still
     * withDefaults().
     */
    public static function withContracts(
        CompletableFrameParserInterface $frameParser,
        InboundMessageClassifierInterface $classifier,
        ?EventParserInterface $eventParser = null,
        ?EventFactoryInterface $eventFactory = null,
        ?ReplyFactory $replyFactory = null,
    ): self {
        return new self(
            frameParser: $frameParser,
            classifier: $classifier,
            eventParser: $eventParser,
            eventFactory: $eventFactory,
            replyFactory: $replyFactory,
        );
    }

    /**
     * Advanced composition path for callers intentionally overriding the
     * current parser/classifier/event/reply collaborators.
     *
     * For the stable public ingress construction path, prefer withDefaults().
     * This constructor remains public for lower-level fixture-backed
     * composition. For contract-based parser/classifier replacement, prefer
     * withContracts().
     */
    public function __construct(
        ?CompletableFrameParserInterface $frameParser = null,
        ?InboundMessageClassifierInterface $classifier = null,
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
            $classifiedFrame = $classified->frame();

            $decoded[] = match (true) {
                $classified->isAuthRequest() => DecodedInboundMessage::forServerAuthRequest(),
                $classified->isDisconnectNotice() => DecodedInboundMessage::forDisconnectNotice(),
                $classified->isEvent() => $this->decodeEventFrame($classifiedFrame),
                $classified->isUnknown() => DecodedInboundMessage::forUnknown(UnknownReply::fromFrame($classifiedFrame)),
                default => DecodedInboundMessage::forReply($this->replyFactory->fromClassification($classified)),
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
