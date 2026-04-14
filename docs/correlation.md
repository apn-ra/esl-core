# Correlation Primitives

`apntalk/esl-core` provides a set of immutable correlation and session metadata primitives in `Apntalk\EslCore\Correlation`. These primitives let upper layers reason about async job lineage, channel event attachment, session traceability, and replay-safe ordering — without binding to any database, service container, or runtime.

---

## The identifier taxonomy

The ESL protocol and this package use several distinct identifier types. Understanding which is which prevents misuse:

| Identifier | Owner | Meaning |
|---|---|---|
| `Unique-ID` | FreeSWITCH | A channel UUID. Identifies a specific call leg. |
| `Job-UUID` | FreeSWITCH | A bgapi job UUID. Assigned on acceptance; used to match the later result. |
| `Event-Sequence` | FreeSWITCH | A monotonically increasing event counter within the FreeSWITCH process. |
| `Core-UUID` | FreeSWITCH | Identifies the FreeSWITCH process instance. Not a channel or job. |
| `ConnectionSessionId` | esl-core | Our identity for one ESL connection session. Not a FreeSWITCH concept. |
| `ObservationSequence` | esl-core | Our monotonic position counter within a session. Not `Event-Sequence`. |

Never substitute one for another.

---

## Value objects

### `ConnectionSessionId`

Immutable identity for a single ESL connection session. Not in the ESL wire protocol.

```php
// Create per connection
$sessionId = ConnectionSessionId::generate();

// Restore from storage
$sessionId = ConnectionSessionId::fromString($storedValue);

// Compare
$sessionId->equals($other);    // true/false
(string) $sessionId;           // UUID string
$sessionId->toString();        // same
```

One `ConnectionSessionId` should be created when a connection opens and used for the lifetime of that connection. Upper layers attach it to `CorrelationContext` and `ReplayEnvelopeFactory`.

---

### `ObservationSequence`

Deterministic 1-based position counter. Assigned by `CorrelationContext` as protocol objects arrive.

```php
$seq = ObservationSequence::first();   // position 1
$seq = ObservationSequence::at(42);    // explicit position (for restore)

$next = $seq->next();                  // immutable advance
$seq->position();                      // int
$seq->isAfter($other);
$seq->isBefore($other);
$seq->equals($other);
```

`ObservationSequence` is NOT `Event-Sequence` from the FreeSWITCH protocol. `Event-Sequence` is assigned by FreeSWITCH and travels in event headers. `ObservationSequence` is assigned by this package and represents the order in which objects were observed on a connection.

---

### `JobCorrelation`

Links a `BgapiAcceptedReply` to its later `BackgroundJobEvent` through the FreeSWITCH-assigned Job-UUID.

```php
// From a bgapi acceptance reply
$corr = JobCorrelation::fromBgapiReply($reply);       // ?self

// From a BACKGROUND_JOB event
$corr = JobCorrelation::fromBackgroundJobEvent($event); // ?self

// Direct construction
$corr = JobCorrelation::fromString($uuid);             // throws on empty

$corr->jobUuid();                                      // string
$corr->matches($uuid);                                 // bool
$corr->equals($other);                                 // bool
```

Upper layers should maintain a registry of pending Job-UUIDs (keyed by `JobCorrelation::jobUuid()`). When a `BACKGROUND_JOB` event arrives with a matching Job-UUID, the registry can route the result to the waiting caller. That registry lives outside `esl-core`.

---

### `ChannelCorrelation`

Carries channel-oriented identifiers from a protocol object. Partial correlation is explicitly modeled — not every protocol object carries all three fields.

```php
// Full context from a NormalizedEvent (uniqueId + channelName + callDirection)
$corr = ChannelCorrelation::fromNormalizedEvent($event);

// Partial — uniqueId only, from a plain EventInterface
$corr = ChannelCorrelation::fromEvent($event);

// Partial — uniqueId only, from a known UUID
$corr = ChannelCorrelation::fromUniqueId($uuid);

// Empty — when no channel context is available
$corr = ChannelCorrelation::unknown();
```

State queries:

```php
$corr->isEmpty();       // all three fields null
$corr->isPartial();     // some but not all fields populated
$corr->canMatch();      // uniqueId is non-null (minimum for matching)
$corr->matches($uuid);  // uniqueId matches given string (false if null)
$corr->equals($other);  // all three fields equal
```

---

## Composite types

### `MessageMetadata`

Metadata snapshot for one observed protocol object. Carries session identity, observation position, timestamp, and all available correlation context.

```php
// Created by CorrelationContext — not constructed directly
$metadata->sessionId();              // ?ConnectionSessionId
$metadata->observationSequence();    // ObservationSequence
$metadata->observedAtMicros();       // int (wall-clock microseconds)
$metadata->jobCorrelation();         // ?JobCorrelation
$metadata->channelCorrelation();     // ?ChannelCorrelation
$metadata->protocolSequence();       // ?string (FreeSWITCH Event-Sequence)

// State queries
$metadata->hasSession();
$metadata->hasJobCorrelation();
$metadata->hasChannelCorrelation();
```

---

### `CorrelationContext`

Stateful per-session factory. One instance per connection. Assigns `ObservationSequence` monotonically and extracts all available correlation from each protocol object.

```php
$sessionId = ConnectionSessionId::generate();
$context   = new CorrelationContext($sessionId);

// Or without explicit session identity (for testing)
$context = CorrelationContext::anonymous();

// Produce metadata for a reply
$metadata = $context->nextMetadataForReply($reply);

// Produce metadata for an event
$metadata = $context->nextMetadataForEvent($event);

// Inspect context state
$context->sessionId();         // ConnectionSessionId
$context->currentSequence();   // ObservationSequence (before next advance)
```

`nextMetadataForReply()` and `nextMetadataForEvent()` each advance the internal sequence. The returned `MessageMetadata` carries the sequence position at the moment of the call.

Correlation extracted automatically:
- `BgapiAcceptedReply` → `JobCorrelation` from reply Job-UUID
- `BACKGROUND_JOB` event → `JobCorrelation` from event Job-UUID
- Channel events (`NormalizedEvent` or typed wrappers) → `ChannelCorrelation`
- All events → `protocolSequence` from `Event-Sequence` header

---

### `EventEnvelope` and `ReplyEnvelope`

Thin wrappers pairing a typed protocol object with its `MessageMetadata`.

```php
$envelope = new EventEnvelope($event, $metadata);
$envelope->event();                // EventInterface
$envelope->metadata();             // MessageMetadata
$envelope->sessionId();            // pass-through
$envelope->observationSequence();  // pass-through
$envelope->jobCorrelation();       // pass-through
$envelope->channelCorrelation();   // pass-through

$envelope = new ReplyEnvelope($reply, $metadata);
$envelope->reply();                // ReplyInterface
$envelope->metadata();             // MessageMetadata
$envelope->sessionId();            // pass-through
$envelope->observationSequence();  // pass-through
$envelope->jobCorrelation();       // pass-through
```

---

## Typical usage pattern

```php
// --- Connection setup ---
$sessionId     = ConnectionSessionId::generate();
$context       = new CorrelationContext($sessionId);
$replayFactory = ReplayEnvelopeFactory::withSession($sessionId);

// --- For each inbound frame ---
// Parse → classify → reply or event → wrap

// Reply path:
$reply         = $replyFactory->fromClassified($classified);
$replyMetadata = $context->nextMetadataForReply($reply);
$replyEnvelope = new ReplyEnvelope($reply, $replyMetadata);

// Event path:
$event         = $eventFactory->fromNormalized($normalized);
$eventMetadata = $context->nextMetadataForEvent($event);
$eventEnvelope = new EventEnvelope($event, $eventMetadata);

// bgapi lineage:
// $replyEnvelope->jobCorrelation()->jobUuid() === $eventEnvelope->jobCorrelation()->jobUuid()
// → upper layer registry routes the result to the caller

// Replay capture (same session):
$replayEnvelope = $replayFactory->fromEventEnvelope($eventEnvelope);
// $replayEnvelope->sessionId() === $sessionId->toString()
```

---

## What CorrelationContext does NOT do

- It does not maintain a pending-job registry.
- It does not route BACKGROUND_JOB results to callers.
- It does not own any I/O, timer, or event loop.
- It does not talk to a database or service container.

Those responsibilities belong in upper-layer packages (`esl-react`, `laravel-freeswitch-esl`).

---

## Relationship to ReplayEnvelope

`CorrelationContext` / `EventEnvelope` / `ReplyEnvelope` and `ReplayEnvelope` / `ReplayEnvelopeFactory` are complementary, not alternatives:

| | Correlation layer | Replay layer |
|---|---|---|
| Carries | Typed objects + rich metadata | Raw payload + classifier context |
| Purpose | Tracing, routing, session visibility | Deterministic reconstruction |
| Serializable | Not designed for it | Yes |
| Session binding | `ConnectionSessionId` typed | String `sessionId` |

`ReplayEnvelopeFactory::withSession(ConnectionSessionId)` lets both substrates share the same session identity, and `fromReplyEnvelope()` / `fromEventEnvelope()` preserve the observation sequence and correlation metadata already assigned by `CorrelationContext`.
