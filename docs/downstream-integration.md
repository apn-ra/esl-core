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
`apntalk/esl-react`, `apntalk/laravel-freeswitch-esl`, and
`apntalk/esl-replay`.

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

- `InboundPipeline::withContracts(...)`
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

These seams are public, but they expose more lower-level coupling than the
preferred path. `InboundPipeline::withContracts(...)` is the additive advanced
path for custom parser/classifier implementations typed against public
contracts, including the public `ClassifiedMessageInterface` classifier result.
Treat these as controlled advanced seams, not the mainstream downstream ingress
model.

### Minimal advanced classifier example

Most downstream packages should not need this path. When a package intentionally
owns frame-level ingress behavior, it can still stay on public contracts:

```php
use Apntalk\EslCore\Contracts\ClassifiedMessageInterface;
use Apntalk\EslCore\Contracts\CompletableFrameParserInterface;
use Apntalk\EslCore\Contracts\InboundMessageClassifierInterface;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Protocol\Frame;

final class MyClassifier implements InboundMessageClassifierInterface
{
    public function classify(Frame $frame): ClassifiedMessageInterface
    {
        // Return a value object implementing ClassifiedMessageInterface.
        // Unknown inputs should still degrade safely instead of throwing.
    }
}

/** @var CompletableFrameParserInterface $parser */
$pipeline = InboundPipeline::withContracts($parser, new MyClassifier());
```

The important compatibility point is the return contract:
`InboundMessageClassifierInterface::classify()` now returns
`ClassifiedMessageInterface`. Existing classifiers that return the package's
current concrete classifier carrier remain compatible because that carrier
implements the public contract, but new downstream implementations no longer
need to import it.

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
- custom typed events only participate in richer normalized-substrate-based
  correlation/replay paths when they implement `ProvidesNormalizedSubstrateInterface`
  intentionally; exposing a similarly named public property is not a supported seam
- classified-message consumption is explicit through `ClassifiedMessageInterface`
  and `ReplyFactory::fromClassification()`
- producer-side classifier returns now use `ClassifiedMessageInterface`, so
  custom classifiers no longer need the current internal carrier
- advanced reply/event construction remains supported through `ReplyFactory`,
  `EventFactory`, and `EventClassifier`, but is not the preferred downstream
  ingress story

## What still belongs outside `esl-core`

`esl-core` remains the protocol/core package. Runtime responsibilities still
belong in upper-layer runtime packages such as `apntalk/esl-react`:

- listener or server runtime ownership
- long-lived read loops or pumps
- reconnect and backoff policy
- heartbeat or session lifecycle supervision
- routing, orchestration, or worker coordination

Framework integration responsibilities belong in packages such as
`apntalk/laravel-freeswitch-esl`:

- Laravel service container bindings
- database-backed registries and persistence
- application-specific configuration and operational endpoints

Replay execution responsibilities belong in replay-focused packages such as
`apntalk/esl-replay`:

- replay scheduling and cursor running
- replay execution or re-injection
- durable replay storage engines

Byte-stream resource policy also stays outside `esl-core`:

- transport-level memory or body-size limits for inbound peers
- connection admission / backpressure policy
- hostile-peer buffering protection around raw parser feeds
- write readiness and retry policy for non-blocking streams; core write calls
  assume the stream is currently writable and do not provide async buffering or
  scheduling

## Release-truth reminder

If a downstream package wants the most conservative integration posture for this
release line:

- prefer the stable seams above
- treat advanced seams as supported but lower-level
- treat softer classifier/parser internals as intentionally deferred rather than
  missing runtime features
