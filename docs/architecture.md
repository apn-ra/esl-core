# Architecture

`apntalk/esl-core` is organized in five layers. Each layer has a clear responsibility boundary. Higher layers consume lower layers; no layer reaches backwards.

---

## Layer overview

```
┌───────────────────────────────────────────────────────┐
│  Layer 5: Transport boundary                          │
│  TransportInterface, InMemoryTransport                │
│  Internal stream-socket smoke transport               │
├───────────────────────────────────────────────────────┤
│  Layer 4: Replay-safe substrate                       │
│  ReplayEnvelope, ReplayCapturePolicy,                 │
│  ReplayCaptureSinkInterface, ReconstructionHookInterface │
│  ReplayEnvelopeFactory (integrates with Correlation)  │
├───────────────────────────────────────────────────────┤
│  Layer 3: Typed domain + Correlation                  │
│  Commands, Replies, Events, EventFactory,             │
│  InboundPipeline                                      │
│  ConnectionSessionId, ObservationSequence             │
│  JobCorrelation, ChannelCorrelation                   │
│  MessageMetadata, CorrelationContext                  │
│  EventEnvelope, ReplyEnvelope                         │
├───────────────────────────────────────────────────────┤
│  Layer 2: Message classification                      │
│  InboundMessageClassifier, MessageType,               │
│  InboundMessageCategory, ClassifiedInboundMessage     │
├───────────────────────────────────────────────────────┤
│  Layer 1: Wire                                        │
│  HeaderBag, Frame, FrameParser, CommandSerializer     │
└───────────────────────────────────────────────────────┘
```

---

## Layer 1 — Wire layer

**Location:** `src/Protocol/`, `src/Parsing/`, `src/Serialization/`

Owns bytes. Knows nothing about protocol semantics beyond structure.

- `HeaderBag` — immutable, case-insensitive header store
- `Frame` — a parsed ESL frame (headers + raw body bytes)
- `FrameParser` — incremental stateful parser; handles partial reads
- `CommandSerializer` — serializes `CommandInterface` → wire bytes

Key invariants:
- `FrameParser` is transport-neutral: it does not own I/O
- `HeaderBag` stores raw (URL-encoded) values
- `Frame` makes no semantic interpretation of the body

---

## Layer 2 — Message classification layer

**Location:** `src/Internal/Classification/`, `src/Protocol/MessageType.php`

Owns protocol meaning. Answers: what category of message is this?

- `MessageType` — backed enum mapping Content-Type strings to categories
- `InboundMessageClassifier` — classifies a `Frame` → `ClassifiedInboundMessage`
- `InboundMessageCategory` — enum of semantic categories (AuthRequest, AuthAccepted, BgapiAccepted, etc.)
- `ClassifiedInboundMessage` — carries the original Frame + its classification

Key invariants:
- Classification is deterministic: same frame → same category
- Unknown content-types degrade to `Unknown` (never throw)
- The bgapi acceptance reply is classified distinctly from ordinary command replies
- Auth failure vs command error cannot be distinguished at this layer (session state required)

---

## Layer 3 — Typed domain layer

**Location:** `src/Commands/`, `src/Replies/`, `src/Events/`, `src/Correlation/`

Owns typed domain objects. Bridges classification and application code.

### Commands
All implement `CommandInterface`. Serialization is on the command itself:
- `AuthCommand`, `ApiCommand`, `BgapiCommand`
- `EventSubscriptionCommand`, `FilterCommand`, `NoEventsCommand`, `ExitCommand`
- `RawCommand` (escape hatch — must end with `\n\n`)

### Replies
All implement `ReplyInterface`. Produced via `ReplyFactory::fromClassified()`:
- `AuthAcceptedReply`, `CommandReply`, `ErrorReply`
- `BgapiAcceptedReply`, `ApiReply`, `UnknownReply`

`ReplyFactory` remains a public lower-level bridge for callers that already own
frame/classifier composition, but it is not the preferred raw-byte ingress path.
`InboundPipeline::withDefaults()` is the preferred public ingress construction
path for upper layers.

### Events
Event parsing: supported event formats (`text/event-plain`, `text/event-json`, provisional `text/event-xml`) → `NormalizedEvent` (via `EventParser`)
Classification: `NormalizedEvent` → typed event (via `EventClassifier`)
Composition: `EventFactory` combines both steps.
Public ingress: `InboundPipeline` composes framing + classification + reply/event decoding for upper layers that should not depend on the provisional concrete parser/classifier classes directly.

`EventFactory` and `EventClassifier` remain public lower-level bridges for
callers that already own a `Frame` or `NormalizedEvent`, but they are not the
preferred raw-byte ingress path. `InboundPipeline::withDefaults()` remains the
dominant supported byte-ingress construction path for upper layers.

- `NormalizedEvent` — normalized header access + raw body, preserving whether the source format was URL-encoded
- `RawEvent` — unknown event safe degradation (wraps `NormalizedEvent`)
- `BackgroundJobEvent`, `ChannelLifecycleEvent`, `BridgeEvent`, `HangupEvent`, `PlaybackEvent`, `CustomEvent`
- `InboundPipeline` — stable byte-oriented facade returning `DecodedInboundMessage`; prefer `InboundPipeline::withDefaults()` for the supported default construction path
- `PreparedInboundConnection` — stable bundle for one accepted-stream bootstrap
- `InboundConnectionFactory` — supported public seam for preparing one accepted stream into transport + pipeline + correlation context

Key invariants:
- Unknown events NEVER throw; they produce `RawEvent`
- `NormalizedEvent.header()` returns normalized values for the source format; `.rawHeader()` preserves the stored source value
- `NormalizedEvent` stays protocol-substrate-only: normalized headers/body/frame truth, not application aggregation or runtime metadata
- `InboundPipeline` is the dominant supported upper-layer ingress path; lower-level parser/classifier composition remains available but provisional
- `EventFactory` / `EventClassifier` remain available for advanced frame/normalized-event composition, not as the default upstream byte-ingress story
- `InboundPipeline::withDefaults()` is the preferred stable ingress construction path; direct constructor collaborator injection remains an advanced composition path that currently couples to provisional concrete collaborators
- `InboundConnectionFactory` prepares one accepted stream but does not own listener loops, session supervision, or transport lifecycle beyond bootstrap
- `BgapiAcceptedReply.jobUuid()` is the correlation key for the later `BackgroundJobEvent`

### Correlation
**Location:** `src/Correlation/`

Provides session identity and correlation metadata primitives for inbound protocol objects.
See `docs/correlation.md` for the full model.

- `ConnectionSessionId` — immutable session identity (UUID v4, one per connection)
- `ObservationSequence` — deterministic 1-based observation ordering within a session
- `JobCorrelation` — links `BgapiAcceptedReply` to a later `BackgroundJobEvent` by Job-UUID
- `ChannelCorrelation` — channel-oriented correlation; partial correlation modeled explicitly
- `MessageMetadata` — composite metadata for one observed protocol object
- `CorrelationContext` — stateful per-session factory that assigns sequences and extracts correlation
- `EventEnvelope` — typed event + `MessageMetadata`
- `ReplyEnvelope` — typed reply + `MessageMetadata`

Key invariants:
- `CorrelationContext` is stateful; one instance per connection
- Partial channel correlation (missing fields) is represented honestly, never faked
- `ConnectionSessionId` is NOT a FreeSWITCH protocol identifier
- `ObservationSequence` is NOT the FreeSWITCH `Event-Sequence` header

---

## Layer 4 — Replay-safe substrate layer

**Location:** `src/Replay/`

Owns replay primitives. Does NOT own a replay runtime.

- `ReplayEnvelope` — carries capture metadata + raw payload for reconstruction
- `ReplayEnvelopeFactory` — produces envelopes from replies/events directly or from correlation envelopes when session/observation metadata already exists
- `ReplayCapturePolicy` — controls which objects are captured
- `ReplayCaptureSinkInterface` — destination for captured envelopes (implemented by upper layers)
- `ReconstructionHookInterface` — called during replay to reconstruct state (implemented by upper layers)

Key invariants:
- `ReplayEnvelope` is deterministic and serializable
- The factory preserves `CorrelationContext` session/observation metadata when built from `ReplyEnvelope` / `EventEnvelope`
- The factory only generates its own monotonic sequence + wall clock when no correlation metadata is supplied
- No durable storage, scheduling, or worker lifecycle in this layer
- `ReplayEnvelopeFactory::withSession(ConnectionSessionId)` binds a factory to a session identity
  shared with `CorrelationContext`, allowing upper layers to use both substrates on the same session

---

## Layer 5 — Transport boundary

**Location:** `src/Transport/`, `src/Internal/Transport/`

Owns minimal I/O abstraction for testability and smoke-path use.

- `TransportInterface` — read/write/close
- `InMemoryTransport` — test double with `enqueueInbound()` / `drainOutbound()`
- `SocketEndpoint` — minimal public endpoint/config value object for socket construction
- `SocketTransportFactory` — stable public seam for connecting or wrapping accepted streams
- `Internal\Transport\StreamSocketTransport` — internal wrapper used by bounded smoke/integration paths over a real PHP stream/socket resource

Key invariants:
- Transport does not own reconnect, supervision, or scheduling
- `InMemoryTransport` is the integration test foundation
- `SocketTransportFactory` is the supported upstream construction seam for downstream packages that need a real byte-stream transport without depending on `Internal\Transport\*`
- Stream/socket smoke transport remains explicitly internal and non-primary, but now covers fragmented, coalesced, delayed-body, delayed-completion, and mid-frame-loss scenarios
- Upper-layer packages (esl-react, laravel-freeswitch-esl) own real transport lifecycle

---

## Namespace policy

| Namespace | Status |
|---|---|
| `Apntalk\EslCore\Contracts` | Public API |
| `Apntalk\EslCore\Commands` | Public API |
| `Apntalk\EslCore\Replies` | Public API |
| `Apntalk\EslCore\Events` | Public API |
| `Apntalk\EslCore\Inbound` | Public API |
| `Apntalk\EslCore\Correlation` | Public API |
| `Apntalk\EslCore\Replay` | Public API (provisional) |
| `Apntalk\EslCore\Capabilities` | Public API |
| `Apntalk\EslCore\Exceptions` | Public API |
| `Apntalk\EslCore\Transport` | Public API |
| `Apntalk\EslCore\Protocol` | Mixed: `Frame` and `HeaderBag` are public substrate value objects; the remaining wire-layer namespace is internal |
| `Apntalk\EslCore\Parsing` | Internal |
| `Apntalk\EslCore\Serialization` | Internal |
| `Apntalk\EslCore\Internal` | Permanently unstable |

---

## Data flow — typical inbound path

```
Raw bytes (from socket or test transport)
  → InboundPipeline.push()/drain() [Layer 3 facade]
    internally:
      → FrameParser.feed() / drain() [Layer 1]
      → InboundMessageClassifier.classify(frame) → ClassifiedInboundMessage [Layer 2]
      → ReplyFactory.fromClassified() or EventParser + EventFactory [Layer 3]

Optionally (correlation):
  → CorrelationContext.nextMetadataForReply/Event() → MessageMetadata [Layer 3]
  → new ReplyEnvelope(reply, metadata)               [Layer 3]
  → new EventEnvelope(event, metadata)               [Layer 3]

Optionally (replay capture):
  → ReplayEnvelopeFactory.fromReplyEnvelope() / fromEventEnvelope() → ReplayEnvelope [Layer 4]
    or ReplayEnvelopeFactory.fromReply() / fromEvent() when correlation metadata is not available
  → ReplayCaptureSinkInterface.capture(envelope)     [upper layer storage]
```

Accepted-stream bootstrap can now be expressed through one supported public seam:

```
Accepted PHP stream
  → InboundConnectionFactory.prepareAcceptedStream()
    → PreparedInboundConnection
      → transport(): TransportInterface
      → pipeline(): InboundPipelineInterface
      → correlationContext(): CorrelationContext
```

If no explicit `ConnectionSessionId` is supplied at bootstrap time, `InboundConnectionFactory`
generates one and binds it to the returned `CorrelationContext`. The seam still stops at
preparation: the caller owns accept loops, read loops, replay bootstrap, and higher-level
session/runtime policy.
