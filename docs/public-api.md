# Public API

This document defines the public API boundary for `apntalk/esl-core`.

The repository uses three consumer postures:

- Preferred public seams: the default downstream integration paths this package wants callers to start from.
- Advanced public seams: public types that remain available for lower-level composition, but expose more concrete or provisional coupling than the preferred path.
- Internal/provisional seams: implementation detail or pre-1.0 composition surfaces that may change without the same compatibility expectations.

## Support-tier map

Use this table as the downstream integration shortcut:

| Posture | What belongs here | Current examples |
|---|---|---|
| Preferred public seams | The default downstream path this package wants upper layers to adopt | `InboundPipeline::withDefaults()`, `InboundConnectionFactory::prepareAcceptedStream()`, `SocketTransportFactory`, typed commands/replies/events, `CorrelationContext`, `ReplayEnvelopeFactory` |
| Advanced public seams | Public composition points that remain supported, but expose more concrete/provisional coupling than the preferred path | `InboundPipeline::__construct(...)`, `ReplyFactory`, `EventFactory`, `EventClassifier`, `FrameParserInterface`, `EventParserInterface`, `InboundMessageClassifierInterface` |
| Internal/provisional seams | Repository implementation details or non-default early-adopter surfaces that may change more freely before `1.0.0` | `Parsing\*`, `Internal\*`, most of `Protocol\*`, internal transport smoke helpers |

Stable capability support and seam posture are related but distinct. A feature can be stable while some lower-level ways of composing that feature remain advanced or provisional.

## What counts as public API

A type, interface, class, or constant is part of the public API when it lives in one of the following namespaces AND is not marked `@internal`, or when it is explicitly exposed by a public contract and marked `@api`:

| Namespace | Contents |
|---|---|
| `Apntalk\EslCore\Contracts` | All interfaces that consumers may depend on |
| `Apntalk\EslCore\Commands` | Typed command objects |
| `Apntalk\EslCore\Replies` | Typed reply objects and the ReplyFactory |
| `Apntalk\EslCore\Events` | Event interfaces, NormalizedEvent, typed event families |
| `Apntalk\EslCore\Inbound` | Stable inbound decoding facade and decoded-message value objects |
| `Apntalk\EslCore\Correlation` | Correlation and session metadata primitives |
| `Apntalk\EslCore\Replay` | Replay envelope and capture contracts |
| `Apntalk\EslCore\Capabilities` | Capability map and support level declarations |
| `Apntalk\EslCore\Exceptions` | Exception hierarchy |
| `Apntalk\EslCore\Transport` | Minimal transport boundary intended for testing and narrow smoke-path use |

Two protocol substrate value objects are also public because public contracts expose them directly:

- `Protocol\Frame`
- `Protocol\HeaderBag`

## What is NOT public API

The following are explicitly unstable and subject to change without notice before `1.0.0`:

- `Apntalk\EslCore\Internal\*` — Implementation details. Do not depend on these.
- `Apntalk\EslCore\Protocol\*` except `Protocol\Frame` and `Protocol\HeaderBag` — Wire-layer primitives. Used internally; not a stable consumer surface.
- `Apntalk\EslCore\Parsing\*` — Parser implementations. Treat as internal.
- `Apntalk\EslCore\Serialization\*` — Serializer implementations. Treat as internal.
- Any class or interface marked `@internal`.

Even when a low-level contract lives in `Contracts\*`, callers should distinguish
between the preferred public seam and advanced or provisional lower-level
building blocks. For upper-layer byte ingestion, `Inbound\InboundPipeline` is the
supported path.

## Pre-1.0 stability rules

Before `1.0.0`:

- The public namespace list above will not shrink.
- Interface signatures in `Contracts` may be revised when fixture-backed behavior requires it.
- New public types will be added additively.
- Breaking changes to public types will be called out explicitly in `CHANGELOG.md`.
- Consumers depending only on listed public namespaces will receive best-effort compatibility within a minor version series.
- `Inbound\InboundPipeline` is now the supported public ingress surface for raw inbound bytes. It intentionally hides the current concrete parser/classifier path under `Parsing` and `Internal`.
- The current concrete inbound parse/classify implementations under `Parsing` and `Internal` remain fixture-backed but provisional and outside the stable public API boundary.

## Interfaces consumers may depend on

These are the core interfaces intended for consumers and upper-layer packages:

```
Contracts\CommandInterface
Contracts\ClassifiedMessageInterface
Contracts\InboundConnectionFactoryInterface
Contracts\InboundPipelineInterface
Contracts\ProvidesNormalizedSubstrateInterface
Contracts\ReplyInterface
Contracts\EventInterface
Contracts\EventFactoryInterface
Contracts\ReplayEnvelopeInterface
Contracts\ReplayCaptureSinkInterface
Contracts\ReconstructionHookInterface
Contracts\CapabilityMapInterface
Contracts\TransportInterface
Contracts\TransportFactoryInterface
```

Lower-level ingress-adjacent contracts such as `FrameParserInterface`,
`EventParserInterface`, and `InboundMessageClassifierInterface` remain present in
`Contracts\*`, but they should be treated as advanced/provisional composition
points rather than the default supported upper-layer integration surface.
Current parser and classifier implementations still exist in the repository and remain fixture-backed, but the concrete classes under `Parsing`, `Protocol`, and `Internal` stay intentionally outside the supported pre-`1.0.0` public API boundary.
Upper layers should prefer `Inbound\InboundPipeline` rather than composing `FrameParser`, `InboundMessageClassifier`, `ReplyFactory`, and `EventFactory` directly.

For downstream packages, the practical split is:

- Build first on `InboundPipeline::withDefaults()`, `SocketTransportFactory`, and `InboundConnectionFactory` when you need supported byte ingress or accepted-stream bootstrap.
- Reach for `ReplyFactory`, `EventFactory`, `EventClassifier`, or low-level parser/classifier contracts only when your package intentionally owns frame-level composition and accepts tighter provisional coupling.
- Do not treat `Parsing\*`, `Internal\*`, or the rest of `Protocol\*` as stable extension seams.

## Concrete types consumers may depend on

### Commands
All command classes in `Apntalk\EslCore\Commands\*` are public.

### Replies
All reply classes in `Apntalk\EslCore\Replies\*` are public.
`ReplyFactory` also remains public, but it is an advanced reply-composition bridge rather than the preferred raw-byte ingress surface. `fromFrame()` is now the explicit advanced entrypoint for callers that already own a `Frame` and want typed reply decoding without passing around the internal classified-message carrier directly. `fromClassification()` is the additive public bridge for callers that already own a classified message and want to type against a public contract instead of the current internal carrier. `fromClassified()` remains available for lower-level fixture-backed composition, but its input is still tied to the lower-level classified-message path, so downstream callers that want the disciplined stable default should prefer `InboundPipeline`.
No soft deprecation is active for `ReplyFactory` in this release line; the hardening change here is clearer posture, not API churn.

### Events
`NormalizedEvent`, `RawEvent`, and typed event families are public.
`NormalizedEvent` remains a substrate object only: normalized headers, raw header access, raw body bytes, and source-format invariants. It does not carry correlation/replay/runtime state.
`Contracts\ProvidesNormalizedSubstrateInterface` is the additive explicit contract for callers that already own a typed event and need the underlying `NormalizedEvent` without relying on concrete property names or reflection-soft coupling. `NormalizedEvent` and the built-in typed wrappers implement this contract.
`EventFactory` and `EventClassifier` also remain public, but they should be treated as advanced event-composition bridges for callers that already own a `Frame` or `NormalizedEvent`. They are not the preferred raw-byte ingress path; upper layers ingesting bytes should still prefer `InboundPipeline`.
Selective typed event families currently include `BackgroundJobEvent`, `ChannelLifecycleEvent`, `BridgeEvent`, `HangupEvent`, `PlaybackEvent`, and `CustomEvent`.
Current live-backed evidence covers bridge/playback decoding in both
`text/event-plain` and `text/event-json`, but the capture helper and PBX setup
used to obtain that evidence remain non-public validation tooling.
`text/event-xml` normalization is now implemented, but remains provisional until
it has broader evidence than the current constructed fixture corpus.

### Inbound
`Inbound\InboundPipeline`, `Inbound\DecodedInboundMessage`, `Inbound\InboundMessageType`, `Inbound\PreparedInboundConnection`, and `Inbound\InboundConnectionFactory` are public.
These types form the supported inbound decoding facade for raw byte ingestion, typed reply/event decoding, normalized-event access, auth-request/disconnect notices, and safe fallback to `RawEvent` / `UnknownReply` where appropriate.
`InboundPipeline::withDefaults()` is the preferred stable construction path for that facade. Direct constructor collaborator injection remains available for advanced composition, but it is not the preferred public ingress path before `1.0.0`.
No soft deprecation is active for `InboundPipeline::__construct(...)` in this release line; the hardening change here is clearer usage guidance, not constructor churn.
`InboundConnectionFactory` is the supported accepted-stream bootstrap seam. It prepares a `PreparedInboundConnection` bundle carrying the wrapped transport, the stable decode facade, and the per-session `CorrelationContext`. If no `ConnectionSessionId` is supplied, the factory generates one for the connection.
For release-boundary purposes, this is the dominant supported ingress contract.
This bootstrap seam stops at one connection bundle. Listener ownership, read pumps, reconnect policy, auth/session state machines, and higher-level supervision remain downstream responsibilities.

### Exceptions
All exception classes in `Apntalk\EslCore\Exceptions\*` are public.

### Transport
`TransportInterface`, `TransportFactoryInterface`, `InMemoryTransport`, `SocketEndpoint`, and `SocketTransportFactory` are public as the minimal transport boundary for testing and narrow smoke-path use.
`SocketTransportFactory` is the supported public construction seam for real byte-stream transports. It can either connect from a `SocketEndpoint` or wrap an already-open PHP stream resource while still returning only `TransportInterface`. Invalid or closed stream inputs fail with `TransportException`.
This does not imply reconnect, scheduling, supervision, or broader transport-runtime ownership in core.
The new stream/socket smoke-path transport remains internal-only under `Internal\Transport\*`; it exists to validate realistic byte-stream behavior, not to widen the supported transport API.

### Protocol substrate exceptions
`Protocol\Frame` and `Protocol\HeaderBag` are stable substrate value objects because they are exposed by public reply/event contracts.
They should not be treated as a signal that the rest of `Protocol\*` is public. The surrounding parser and classifier pipeline remains advanced/provisional outside the preferred ingress facade.

## Later-phase hardening notes

These items are being documented for future hardening, not redesigned in this pass:

- `InboundPipeline::withDefaults()` remains the default downstream ingress seam; the constructor stays public as an advanced composition escape hatch without an active deprecation.
- `ReplyFactory::fromFrame()` is now the explicit advanced reply bridge for frame-owned composition, while `fromClassification()` is the additive public classified-message bridge. `fromClassified()` and the classifier interface still remain more provisional and lower-level.
- `ReplyFactory`, `EventFactory`, and `EventClassifier` remain public advanced bridges and are not being promoted into the mainstream downstream ingress story.
- `Contracts\ClassifiedMessageInterface` is the public read-only contract for advanced classified-message access. `InboundMessageClassifierInterface` still returns the current internal carrier for compatibility, but that carrier now implements the public contract.
- `FrameParserInterface`, `EventParserInterface`, and `InboundMessageClassifierInterface` remain public-but-provisional until downstream usage proves they deserve stronger compatibility guarantees.
- `DecodedInboundMessage::normalizedEvent()` remains the supported normalized-event substrate access point for downstream byte-ingress consumers, while `Contracts\ProvidesNormalizedSubstrateInterface` is the explicit additive contract for callers that already own a typed event instance; no new classified-message or parser-owned public seam is added in this pass.

The error taxonomy is intentionally layered:
- `TransportException` covers I/O/connection failures only
- `ParseException` is the common parser superclass, with more specific subtypes including `MalformedFrameException`, `TruncatedFrameException`, and `UnsupportedContentTypeException`
- `UnexpectedReplyException` signals structurally incompatible typed-reply construction
- `ReplayException` and `ReplayConsistencyException` cover replay-envelope construction assumptions

## Raw escape hatches

The `RawCommand` and `RawEvent` classes exist as explicit raw-data escape hatches. They are part of the public API but should not be the primary integration point.
