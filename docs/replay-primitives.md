# Replay Primitives

This document describes the replay-safe substrate provided by `apntalk/esl-core`.

## Scope

`apntalk/esl-core` is **replay-safe**, not **replay-complete**.

It provides:
- `ReplayEnvelope` — deterministic envelope shape for captured protocol objects
- `ReplayEnvelopeFactory` — produces envelopes from replies and events
- `ReplayCapturePolicy` — controls what gets captured
- `ReplayCaptureSinkInterface` — where captured envelopes go (implemented by upper layers)
- `ReconstructionHookInterface` — how captured data is replayed (implemented by upper layers)

It does **not** provide:
- Durable envelope storage (that's an upper-layer concern)
- Replay scheduling (upper layer)
- Worker lifecycle for replay execution (upper layer)
- Distributed replay coordination (upper layer)

---

## ReplayEnvelope

A `ReplayEnvelope` captures:

| Field | Type | Description |
|---|---|---|
| `capturedType` | `string` | `'reply'` or `'event'` |
| `capturedName` | `string` | Short class name (replies) or event name (events) |
| `sessionId` | `?string` | Connection session identifier, if provided |
| `captureSequence` | `int` | Monotonically increasing within a factory instance |
| `capturedAtMicros` | `int` | Wall-clock capture time in microseconds |
| `protocolSequence` | `?string` | `Event-Sequence` from the ESL protocol, if present |
| `rawPayload` | `string` | Raw header block (replies) or frame body (events) |
| `classifierContext` | `array<string, string>` | Fields needed for protocol-level reconstruction |
| `protocolFacts` | `array<string, string>` | Protocol-native facts preserved separately from derived metadata |
| `derivedMetadata` | `array<string, string>` | Session/correlation metadata assigned by `esl-core` |

---

## ReplayEnvelopeFactory

```php
$factory = new ReplayEnvelopeFactory(sessionId: 'session-abc');

// Capture a reply
$envelope = $factory->fromReply($bgapiAcceptedReply);

// Capture an event
$envelope = $factory->fromNormalizedEvent($event->normalized());

// Capture a typed event
$envelope = $factory->fromEvent($channelCreateEvent);

// Prefer this path when CorrelationContext has already attached metadata
$replyEnvelope = new ReplyEnvelope($reply, $replyMetadata);
$envelope = $factory->fromReplyEnvelope($replyEnvelope);
```

When `fromReplyEnvelope()` / `fromEventEnvelope()` is used, replay capture preserves:
- `ConnectionSessionId`
- `ObservationSequence` as `captureSequence`
- `observedAtMicros` as `capturedAtMicros`
- protocol-native identifiers such as `Job-UUID`, `Unique-ID`, `Event-Sequence`, and `Event-Date-Timestamp`
- derived metadata such as `JobCorrelation` and `ChannelCorrelation`

The direct `fromReply()` / `fromEvent()` methods remain available for narrower use cases
where correlation metadata has not been attached yet. In that mode, sequence numbers
increment per factory instance. Use one factory per session.

---

## ReplayCapturePolicy

Controls what gets captured:

```php
// Capture everything
$policy = ReplayCapturePolicy::captureAll();

// Capture only replies
$policy = ReplayCapturePolicy::repliesOnly();

// Capture events but exclude HEARTBEAT
$policy = new ReplayCapturePolicy(
    captureReplies:    true,
    captureEvents:     true,
    excludeEventNames: ['HEARTBEAT'],
);

// Use in your event loop
if ($policy->shouldCaptureEvent($event)) {
    $sink->capture($factory->fromEvent($event));
}
```

---

## ReplayCaptureSinkInterface

```php
interface ReplayCaptureSinkInterface
{
    public function capture(ReplayEnvelopeInterface $envelope): void;
}
```

The sink is implemented by upper-layer packages. Examples:
- `InMemoryCaptureSink` (for testing) — stores in an array
- `DatabaseCaptureSink` (laravel layer) — writes to a DB table
- `FileSink` — appends to a file

---

## ReconstructionHookInterface

```php
interface ReconstructionHookInterface
{
    public function handles(ReplayEnvelopeInterface $envelope): bool;
    public function apply(ReplayEnvelopeInterface $envelope): void;
}
```

Reconstruction hooks are called during a replay pass to reconstitute application state from captured envelopes. The replay execution engine that drives the pass lives in upper-layer packages.

---

## Determinism requirement

For replay to work correctly, the envelope shape must be deterministic.

- The same protocol object must produce the same `rawPayload` every time.
- `captureSequence` must be monotonically increasing within a session.
- `capturedAtMicros` may vary (it's wall-clock time) — reconstruction hooks should use `protocolSequence` or `captureSequence` for ordering, not `capturedAtMicros`.
- `protocolFacts` must remain protocol-truthful and separate from `derivedMetadata`.

---

## What belongs in upper layers

| Concern | Package |
|---|---|
| Durable storage of envelopes | `apntalk/laravel-freeswitch-esl` or similar |
| Replay scheduling and cursor tracking | upper layer |
| Worker allocation for replay jobs | upper layer |
| Distributed replay coordination | upper layer |
| Operational replay control plane (start/stop/status) | upper layer |
