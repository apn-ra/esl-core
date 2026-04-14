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

## Pre-1.0 stability rules

Before `1.0.0`:

- The public namespace list above will not shrink.
- Interface signatures in `Contracts` may be revised when fixture-backed behavior requires it.
- New public types will be added additively.
- Breaking changes to public types will be called out explicitly in `CHANGELOG.md`.
- Consumers depending only on listed public namespaces will receive best-effort compatibility within a minor version series.
- The current inbound parse/classify path still relies on concrete implementations under `Parsing` and `Internal`; those implementations are fixture-backed but remain provisional and outside the stable public API boundary.

## Interfaces consumers may depend on

These are the core interfaces intended for consumers and upper-layer packages:

```
Contracts\FrameParserInterface
Contracts\FrameSerializerInterface
Contracts\CommandInterface
Contracts\ReplyInterface
Contracts\EventInterface
Contracts\EventParserInterface
Contracts\EventFactoryInterface
Contracts\InboundMessageClassifierInterface
Contracts\ReplayEnvelopeInterface
Contracts\ReplayCaptureSinkInterface
Contracts\ReconstructionHookInterface
Contracts\CapabilityMapInterface
Contracts\TransportInterface
```

Current parser and classifier implementations exist in the repository and are fixture-backed, but the concrete classes under `Parsing`, `Protocol`, and `Internal` remain intentionally outside the supported pre-`1.0.0` public API boundary.
Careful adopters may compose those implementations directly today for the full inbound pipeline, but they should do so as an early-adopter/provisional integration rather than a stable API commitment.

## Concrete types consumers may depend on

### Commands
All command classes in `Apntalk\EslCore\Commands\*` are public.

### Replies
All reply classes in `Apntalk\EslCore\Replies\*`, plus `ReplyFactory`, are public.

### Events
`NormalizedEvent`, `RawEvent`, and typed event families are public.
Selective typed event families currently include `BackgroundJobEvent`, `ChannelLifecycleEvent`, `BridgeEvent`, `HangupEvent`, `PlaybackEvent`, and `CustomEvent`.

### Exceptions
All exception classes in `Apntalk\EslCore\Exceptions\*` are public.

### Transport
`TransportInterface` and `InMemoryTransport` are public as the minimal transport boundary for testing and narrow smoke-path use.
This does not imply reconnect, scheduling, supervision, or broader transport-runtime ownership in core.

The error taxonomy is intentionally layered:
- `TransportException` covers I/O/connection failures only
- `ParseException` is the common parser superclass, with more specific subtypes including `MalformedFrameException`, `TruncatedFrameException`, and `UnsupportedContentTypeException`
- `UnexpectedReplyException` signals structurally incompatible typed-reply construction
- `ReplayException` and `ReplayConsistencyException` cover replay-envelope construction assumptions

## Raw escape hatches

The `RawCommand` and `RawEvent` classes exist as explicit raw-data escape hatches. They are part of the public API but should not be the primary integration point.
