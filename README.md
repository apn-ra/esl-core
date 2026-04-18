# apntalk/esl-core

**Framework-agnostic, transport-neutral, typed FreeSWITCH ESL protocol substrate for PHP with replay-safe primitives.**

---

## What this package is

`apntalk/esl-core` is a **protocol substrate** for FreeSWITCH Event Socket Layer (ESL) clients.

It provides:

- A truthful ESL wire model (framing, parsing, serialization)
- Deterministic message classification (auth, command replies, events, bgapi)
- Typed command and reply objects
- A normalized event model with selective typed event families and safe degradation for unknown event types
- Correlation and session metadata primitives
- Replay-safe protocol envelopes and reconstruction-oriented hook contracts
- Capability declaration of supported surfaces
- A minimal transport abstraction for testing and smoke-path use

It is designed to sit **below** runtime, framework, and replay packages such as
`apntalk/esl-react`, `apntalk/laravel-freeswitch-esl`, and
`apntalk/esl-replay`.

---

## What this package is not

This package does **not** provide:

- Laravel service container bindings
- ReactPHP or Amp event loop integration
- Reconnect or supervision logic
- Worker assignment or routing
- Cluster or multi-PBX orchestration
- Database-backed registry behavior
- Durable replay execution engines
- Replay re-injection or replay scheduling
- Health endpoints

Those concerns belong in upper-layer packages that depend on this one:
`apntalk/esl-react` owns runtime/reconnect behavior,
`apntalk/laravel-freeswitch-esl` owns Laravel integration and persistence
concerns, and `apntalk/esl-replay` owns replay execution/re-injection.

---

## Requirements

- PHP 8.1 or higher
- No runtime framework dependencies

---

## Installation

```bash
composer require apntalk/esl-core
```

---

## Stability

This package follows [SemVer](https://semver.org/), but it is still pre-`1.0.0`.

- Public namespaces are documented in [`docs/public-api.md`](docs/public-api.md)
- The supported inbound decode surface is now `Apntalk\EslCore\Inbound\InboundPipeline`
- Internal parser/classifier implementations remain intentionally unstable before `1.0.0`
- Replay envelopes and reconstruction-oriented contracts should be treated as provisional surfaces until `1.0.0`

See [`docs/stability-policy.md`](docs/stability-policy.md) for full details.

---

## Architecture overview

The library is organized in layers:

| Layer | Responsibility |
|---|---|
| Wire | Bytes, headers, body, framing, parsing, serialization |
| Classification | Session/auth state, message category, reply vs event distinction |
| Typed domain | Commands, replies, normalized events, correlation metadata |
| Replay substrate | Replay envelopes, capture policies, reconstruction hooks |
| Transport boundary | Minimal read/write contracts, in-memory transport |

See [`docs/architecture.md`](docs/architecture.md) for the full architecture description.

---

## Quick start

The supported public surface is centered on typed commands, the inbound decoding facade, normalized/typed events, correlation metadata, replay envelopes, capabilities, and the minimal transport boundary.
For new integrations, start from `InboundPipeline::withDefaults()` for raw byte decoding, `SocketTransportFactory` for endpoint/stream transport construction, and `InboundConnectionFactory` when a listener/runtime has already accepted a stream and needs one supported bootstrap bundle.
Direct `InboundPipeline::__construct(...)` collaborator injection and parser/classifier/reply-factory composition remain available for advanced fixture-backed work, but they are not the preferred downstream ingress path at this checkpoint and are not being soft-deprecated in this release line.

For one concise downstream integration map, see [`docs/downstream-integration.md`](docs/downstream-integration.md).

### Downstream integration map

For packages such as `apntalk/laravel-freeswitch-esl`, the supported integration choices are:

| Downstream need | Preferred public seam | Ownership that stays outside `esl-core` |
|---|---|---|
| Open a client connection from host/port settings | `SocketTransportFactory::connect()` + `InboundPipeline::withDefaults()` | reconnect/backoff, read loops, auth/session policy, event subscription policy |
| Bootstrap one already-accepted inbound stream | `InboundConnectionFactory::prepareAcceptedStream()` | listener ownership, accept loops, per-session supervision |
| Compose directly from frames / normalized events | `ReplyFactory::fromFrame()`, `ReplyFactory::fromClassification()`, `EventFactory`, `EventClassifier`, lower-level contracts | byte-ingress defaults, stable constructor ergonomics, protection from provisional coupling |

Use `CorrelationContext` after decode when your upper layer needs per-session ordering or derived job/channel correlation. Use `ReplayEnvelopeFactory` only for replay-safe capture/export hooks; storage, scheduling, replay execution, and replay re-injection stay in upper layers such as `apntalk/esl-replay`.

### Preferred ingress facade

Use `InboundPipeline::withDefaults()` when you need the supported raw-byte decode path without coupling to the current parser/classifier implementation details.

```php
use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Inbound\InboundPipeline;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Transport\InMemoryTransport;

// InMemoryTransport is a test/smoke transport, not a runtime owner.
$transport = new InMemoryTransport();
$transport->write((new AuthCommand('ClueCon'))->serialize());

$inbound = InboundPipeline::withDefaults();
$transport->enqueueInbound("Content-Type: auth/request\n\n");
$messages = $inbound->decode($transport->read(4096) ?? '');
$messages[0]->isServerAuthRequest(); // true

$sessionId = ConnectionSessionId::generate();
$correlation = new CorrelationContext($sessionId);
$replay = ReplayEnvelopeFactory::withSession($sessionId);
```

### Preferred transport construction seam

Use `SocketTransportFactory` when you need core to create or wrap a real byte-stream transport while keeping lifecycle policy outside `esl-core`.

```php
use Apntalk\EslCore\Transport\SocketEndpoint;
use Apntalk\EslCore\Transport\SocketTransportFactory;

$socketFactory = new SocketTransportFactory();
$transport = $socketFactory->connect(SocketEndpoint::tcp('127.0.0.1', 8021));
```

### Accepted-stream bootstrap seam

Use `InboundConnectionFactory` when your listener/runtime has already accepted a PHP stream and now needs the supported core bootstrap bundle.

```php
use Apntalk\EslCore\Inbound\InboundConnectionFactory;

[$acceptedPhpStream] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
$acceptedFactory = new InboundConnectionFactory();
$prepared = $acceptedFactory->prepareAcceptedStream($acceptedPhpStream);
$prepared->pipeline()->push($prepared->transport()->read(4096) ?? '');
```

In production, `$acceptedPhpStream` is provided by your listener/runtime layer after accept. If `prepareAcceptedStream()` is called without a `ConnectionSessionId`, core generates one for that connection and binds it to the returned `CorrelationContext`. That bootstrap step still does not imply listener ownership, a read loop, replay bootstrap integration, or any higher-level session supervision.

If you need the current low-level parser/classifier implementations directly, they are still available in the repository and fixture-backed, but they remain pre-1.0 unstable implementation surfaces rather than the disciplined public API boundary.
Upper layers should prefer `InboundPipeline::withDefaults()` instead of composing `FrameParser`, `InboundMessageClassifier`, `ReplyFactory`, and `EventFactory` directly, unless they intentionally need frame-level control and accept provisional coupling to lower-level collaborators.
For that advanced composition path, the current staged migration posture is:

- consume classified output through `Contracts\\ClassifiedMessageInterface`
- pass it to `ReplyFactory::fromClassification()` when you need typed replies
- avoid treating `InboundMessageClassifierInterface` itself as a fully hardened public producer contract yet

### Preferred vs advanced seam posture

| Posture | What to build on first |
|---|---|
| Preferred public seams | `InboundPipeline::withDefaults()`, `SocketTransportFactory`, `InboundConnectionFactory`, typed commands/replies/events, `CorrelationContext`, `ReplayEnvelopeFactory` |
| Advanced public seams | `InboundPipeline::__construct(...)`, `ReplyFactory::fromFrame()`, `ReplyFactory::fromClassification()`, `ReplyFactory::fromClassified()`, `EventFactory`, `EventClassifier`, `Contracts\ClassifiedMessageInterface`, `Contracts\ProvidesNormalizedSubstrateInterface`, `Contracts\FrameSerializerInterface`, lower-level `Contracts\*` parser/classifier interfaces |
| Internal or provisional implementation details | `Parsing\*`, `Internal\*`, most of `Protocol\*` other than `Frame` and `HeaderBag` |

## Current release scope

- Typed commands and replies for auth, command replies, `api`, and `bgapi`
- Stable inbound byte-stream decoding via `InboundPipeline`
- Stable accepted-stream inbound bootstrap via `InboundConnectionFactory` + `PreparedInboundConnection`
- Normalized events for `text/event-plain` and `text/event-json`
- Provisional normalized event decoding for `text/event-xml`
- Selective typed event families: background job, channel lifecycle, bridge, hangup, playback, and custom events
- Correlation/session metadata and replay-safe envelopes
- Minimal in-memory transport and explicit failure taxonomy
- Stable public socket transport construction via `SocketEndpoint` + `SocketTransportFactory`
- Internal-only stream/socket smoke-path validation over a real PHP stream resource
- Fixture-backed behavior, PHPUnit coverage, PHPStan, and capability verification

Still provisional or deferred from this release:
- live-backed `text/event-xml` evidence beyond constructed fixtures
- framework/runtime integrations
- broader transport runtime expansion beyond the minimal socket construction seam
- replay storage, scheduling, execution, re-injection, or orchestration

## Smoke check

For a fast confidence pass that the current substrate composes cleanly on its happy paths, run:

```bash
composer smoke
```

This smoke path exercises the supported inbound facade together with the typed command/reply and async event pipelines, including correlation/session metadata and replay-envelope creation.

## Maintainer verification

Use the narrowest useful check first:

- `composer unit` for low-level value-object and wire-model regressions
- `composer contract` for public seam and fixture-backed behavior checks
- `composer integration` for composed in-memory/socket/inbound-path verification
- `composer smoke` for a fast supported-path sanity pass
- `composer check` for the full local release gate (`cs-check`, `analyse`, and `test`)
- `composer validate --strict` when changing package metadata or Composer scripts

Live `tools/smoke/*` helpers remain optional operator validation support for fixture work and PBX-side evidence gathering. They are not part of the package API or the default local release gate.

## Current release-line status

The repository is currently positioned as **a small pre-`1.0.0` release line with the core seams in place and residual provisional surfaces explicitly documented**.
That means:

- the supported ingress contract is explicit and documented around `InboundPipeline`
- XML event decoding exists, but is still declared provisional pending broader evidence
- stream/socket validation is stronger, but remains internal smoke support only
- residual pre-1.0 gaps are documented rather than hidden

For shipped version history and current unreleased changes, treat
[`CHANGELOG.md`](CHANGELOG.md) plus the published git tags/GitHub releases as
the release source of truth. Historical draft notes under `docs/releases/`
remain maintainer context only.

---

## Documentation

- [`docs/architecture.md`](docs/architecture.md)
- [`docs/protocol-model.md`](docs/protocol-model.md)
- [`docs/protocol-state.md`](docs/protocol-state.md)
- [`docs/fixtures.md`](docs/fixtures.md)
- [`docs/replay-primitives.md`](docs/replay-primitives.md)
- [`docs/public-api.md`](docs/public-api.md)
- [`docs/downstream-integration.md`](docs/downstream-integration.md)
- [`docs/stability-policy.md`](docs/stability-policy.md)
- [`docs/capabilities.md`](docs/capabilities.md)
- [`docs/release-checklist.md`](docs/release-checklist.md)

---

## License

MIT. See [LICENSE](LICENSE).
