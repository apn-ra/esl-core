<?php

declare(strict_types=1);

use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Correlation\EventEnvelope;
use Apntalk\EslCore\Correlation\ReplyEnvelope;
use Apntalk\EslCore\Events\EventFactory;
use Apntalk\EslCore\Internal\Classification\InboundMessageCategory;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Replies\ReplyFactory;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$raw = stream_get_contents(STDIN);
if (!is_string($raw) || $raw === '') {
    fwrite(STDERR, "Provide a raw ESL frame on stdin.\n");
    exit(2);
}

$parser = new FrameParser();
$parser->feed($raw);
$frames = $parser->drain();

if (count($frames) !== 1) {
    fwrite(STDERR, sprintf("Expected exactly one frame, got %d.\n", count($frames)));
    exit(1);
}

$classifier = new InboundMessageClassifier();
$classified = $classifier->classify($frames[0]);
$context = new CorrelationContext(ConnectionSessionId::generate());
$replay = ReplayEnvelopeFactory::withSession($context->sessionId());

$payload = [
    'classified' => [
        'category' => $classified->category->name,
        'message_type' => $classified->messageType->value,
        'headers' => $classified->frame->headers->toArray(),
        'body' => $classified->frame->body,
    ],
];

if ($classified->category === InboundMessageCategory::EventMessage) {
    $event = (new EventFactory())->fromFrame($classified->frame);
    $metadata = $context->nextMetadataForEvent($event);
    $envelope = new EventEnvelope($event, $metadata);
    $replayEnvelope = $replay->fromEventEnvelope($envelope);

    $payload['typed'] = [
        'class' => $event::class,
        'event_name' => $event->eventName(),
        'event_sequence' => $event->eventSequence(),
        'unique_id' => $event->uniqueId(),
        'job_uuid' => $event->jobUuid(),
        'replay' => [
            'captured_name' => $replayEnvelope->capturedName(),
            'protocol_facts' => $replayEnvelope->protocolFacts(),
            'derived_metadata' => $replayEnvelope->derivedMetadata(),
        ],
    ];
} else {
    $reply = (new ReplyFactory())->fromClassified($classified);
    $metadata = $context->nextMetadataForReply($reply);
    $envelope = new ReplyEnvelope($reply, $metadata);
    $replayEnvelope = $replay->fromReplyEnvelope($envelope);

    $payload['typed'] = [
        'class' => $reply::class,
        'is_success' => $reply->isSuccess(),
        'replay' => [
            'captured_name' => $replayEnvelope->capturedName(),
            'protocol_facts' => $replayEnvelope->protocolFacts(),
            'derived_metadata' => $replayEnvelope->derivedMetadata(),
        ],
    ];
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
