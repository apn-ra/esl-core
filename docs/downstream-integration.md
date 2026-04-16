# Downstream Integration

This note gives downstream packages one concise integration map for the current
`apntalk/esl-core` release line.

Use it alongside:

- [Public API](public-api.md)
- [Stability Policy](stability-policy.md)
- [Architecture](architecture.md)

## Preferred downstream composition path

For new downstream work, start from the stable public seams:

1. `SocketTransportFactory` when core should construct or wrap the byte-stream transport
2. `InboundPipeline::withDefaults()` when you need supported raw-byte ingress into typed messages
3. `CorrelationContext` when your upper layer needs per-session ordering or derived job/channel correlation
4. `ReplayEnvelopeFactory` only when you need replay-safe capture/export primitives

That is the preferred core composition story for packages such as
`apntalk/laravel-freeswitch-esl`.

## Accepted-stream bootstrap path

Use `InboundConnectionFactory::prepareAcceptedStream()` when your listener or
runtime has already accepted a PHP stream and now needs a supported bootstrap
bundle for one inbound ESL connection.

That bundle contains:

- wrapped `TransportInterface`
- `InboundPipeline`
- per-session `CorrelationContext`

This seam stops at one prepared connection. It does not imply listener
ownership, accept loops, reconnect policy, read pumps, or higher-level session
supervision.

## Preferred vs advanced vs internal

### Preferred public seams

Build on these first:

- `SocketTransportFactory`
- `InboundConnectionFactory`
- `InboundPipeline::withDefaults()`
- typed commands, replies, and events
- `CorrelationContext`
- `ReplayEnvelopeFactory`

### Advanced public seams

Use these only when your package intentionally owns lower-level composition:

- `InboundPipeline::__construct(...)`
- `ReplyFactory::fromFrame()`
- `ReplyFactory::fromClassification()`
- `ReplyFactory::fromClassified()`
- `EventFactory`
- `EventClassifier`
- `Contracts\ClassifiedMessageInterface`
- `Contracts\ProvidesNormalizedSubstrateInterface`
- `Contracts\FrameSerializerInterface`
- lower-level parser/classifier contracts under `Contracts\*`

These seams are public, but they expose more concrete or provisional coupling
than the preferred path.

### Internal or provisional surfaces

Do not treat these as stable extension points:

- `Internal\*`
- `Parsing\*`
- most of `Protocol\*` other than `Frame` and `HeaderBag`
- current concrete serializer/parser/classifier implementations

## Advanced seam clarity

These issues are documented, but not treated as release blockers in this line:

- normalized-substrate access is explicit through `ProvidesNormalizedSubstrateInterface`
  and `DecodedInboundMessage::normalizedEvent()`
- classified-message consumption is explicit through `ClassifiedMessageInterface`
  and `ReplyFactory::fromClassification()`
- producer-side classifier returns remain softer because `InboundMessageClassifierInterface`
  still returns the current internal carrier for compatibility
- advanced reply/event construction remains supported through `ReplyFactory`,
  `EventFactory`, and `EventClassifier`, but is not the preferred downstream
  ingress story

## What still belongs to `esl-react`

`esl-core` remains the protocol/core package. These responsibilities still
belong in upper-layer runtime packages such as `apntalk/esl-react`:

- listener or server runtime ownership
- long-lived read loops or pumps
- reconnect and backoff policy
- heartbeat or session lifecycle supervision
- routing, orchestration, or worker coordination
- replay execution/runtime orchestration

## Release-truth reminder

If a downstream package wants the most conservative integration posture for this
release line:

- prefer the stable seams above
- treat advanced seams as supported but more concrete
- treat softer classifier/parser internals as intentionally deferred rather than
  missing runtime features
