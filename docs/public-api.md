# Public API

This document defines the public API boundary for `apntalk/esl-core`.

## What counts as public API

A type, interface, class, or constant is part of the public API when it lives in one of the following namespaces AND is not marked `@internal`:

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

## What is NOT public API

The following are explicitly unstable and subject to change without notice before `1.0.0`:

- `Apntalk\EslCore\Internal\*` — Implementation details. Do not depend on these.
- `Apntalk\EslCore\Protocol\*` — Wire-layer primitives. Used internally; not a stable consumer surface.
- `Apntalk\EslCore\Parsing\*` — Parser implementations. Treat as internal.
- `Apntalk\EslCore\Serialization\*` — Serializer implementations. Treat as internal.
- Any class or interface marked `@internal`.

Even when a low-level contract lives in `Contracts\*`, callers should distinguish
between the supported ingress contract and the provisional low-level ingress
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
Contracts\InboundPipelineInterface
Contracts\ReplyInterface
Contracts\EventInterface
Contracts\EventFactoryInterface
Contracts\ReplayEnvelopeInterface
Contracts\ReplayCaptureSinkInterface
Contracts\ReconstructionHookInterface
Contracts\CapabilityMapInterface
Contracts\TransportInterface
```

Lower-level ingress-adjacent contracts such as `FrameParserInterface`,
`EventParserInterface`, and `InboundMessageClassifierInterface` remain present in
`Contracts\*`, but they should be treated as advanced/provisional composition
points rather than the default supported upper-layer integration surface.
Current parser and classifier implementations still exist in the repository and remain fixture-backed, but the concrete classes under `Parsing`, `Protocol`, and `Internal` stay intentionally outside the supported pre-`1.0.0` public API boundary.
Upper layers should prefer `Inbound\InboundPipeline` rather than composing `FrameParser`, `InboundMessageClassifier`, `ReplyFactory`, and `EventFactory` directly.

## Concrete types consumers may depend on

### Commands
All command classes in `Apntalk\EslCore\Commands\*` are public.

### Replies
All reply classes in `Apntalk\EslCore\Replies\*`, plus `ReplyFactory`, are public.

### Events
`NormalizedEvent`, `RawEvent`, and typed event families are public.
`NormalizedEvent` remains a substrate object only: normalized headers, raw header access, raw body bytes, and source-format invariants. It does not carry correlation/replay/runtime state.
Selective typed event families currently include `BackgroundJobEvent`, `ChannelLifecycleEvent`, `BridgeEvent`, `HangupEvent`, `PlaybackEvent`, and `CustomEvent`.
Current live-backed evidence covers bridge/playback decoding in both
`text/event-plain` and `text/event-json`, but the capture helper and PBX setup
used to obtain that evidence remain non-public validation tooling.
`text/event-xml` normalization is now implemented, but remains provisional until
it has broader evidence than the current constructed fixture corpus.

### Inbound
`Inbound\InboundPipeline`, `Inbound\DecodedInboundMessage`, and `Inbound\InboundMessageType` are public.
These types form the supported inbound decoding facade for raw byte ingestion, typed reply/event decoding, normalized-event access, auth-request/disconnect notices, and safe fallback to `RawEvent` / `UnknownReply` where appropriate.
For release-boundary purposes, this is the dominant supported ingress contract.

### Exceptions
All exception classes in `Apntalk\EslCore\Exceptions\*` are public.

### Transport
`TransportInterface` and `InMemoryTransport` are public as the minimal transport boundary for testing and narrow smoke-path use.
This does not imply reconnect, scheduling, supervision, or broader transport-runtime ownership in core.
The new stream/socket smoke-path transport remains internal-only under `Internal\Transport\*`; it exists to validate realistic byte-stream behavior, not to widen the supported transport API.

The error taxonomy is intentionally layered:
- `TransportException` covers I/O/connection failures only
- `ParseException` is the common parser superclass, with more specific subtypes including `MalformedFrameException`, `TruncatedFrameException`, and `UnsupportedContentTypeException`
- `UnexpectedReplyException` signals structurally incompatible typed-reply construction
- `ReplayException` and `ReplayConsistencyException` cover replay-envelope construction assumptions

## Raw escape hatches

The `RawCommand` and `RawEvent` classes exist as explicit raw-data escape hatches. They are part of the public API but should not be the primary integration point.
