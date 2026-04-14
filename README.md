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

It is designed to sit **below** framework-specific packages such as `apntalk/esl-react` and `apntalk/laravel-freeswitch-esl`.

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
- Health endpoints

Those concerns belong in upper-layer packages that depend on this one.

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

The supported public surface is centered on typed commands, events, correlation metadata, replay envelopes, capabilities, and the minimal transport boundary.

```php
use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Correlation\ConnectionSessionId;
use Apntalk\EslCore\Correlation\CorrelationContext;
use Apntalk\EslCore\Replay\ReplayEnvelopeFactory;
use Apntalk\EslCore\Transport\InMemoryTransport;

$transport = new InMemoryTransport();
$transport->write((new AuthCommand('ClueCon'))->serialize());

$sessionId = ConnectionSessionId::generate();
$correlation = new CorrelationContext($sessionId);
$replay = ReplayEnvelopeFactory::withSession($sessionId);
```

If you need the current low-level parser/classifier implementations directly, they are available in the repository and are fixture-backed, but they remain pre-1.0 unstable implementation surfaces rather than the disciplined public API boundary.
That means careful adopters can compose the full inbound parse/classify pipeline today, but should treat that composition as an early-adopter/provisional path until a later pre-`1.0.0` release hardens the ingress story further.

## Current release scope

- Typed commands and replies for auth, command replies, `api`, and `bgapi`
- Normalized events for `text/event-plain` and `text/event-json`
- Selective typed event families: background job, channel lifecycle, bridge, hangup, playback, and custom events
- Correlation/session metadata and replay-safe envelopes
- Minimal in-memory transport and explicit failure taxonomy
- Fixture-backed behavior, PHPUnit coverage, PHPStan, and capability verification

Deferred from this release:
- `text/event-xml`
- framework/runtime integrations
- transport expansion beyond `InMemoryTransport`
- replay storage, scheduling, or orchestration

## Smoke check

For a fast confidence pass that the current substrate composes cleanly on its happy paths, run:

```bash
composer smoke
```

This smoke path exercises the existing typed command/reply and async event pipelines, including correlation/session metadata and replay-envelope creation.

---

## Documentation

- [`docs/architecture.md`](docs/architecture.md)
- [`docs/protocol-model.md`](docs/protocol-model.md)
- [`docs/protocol-state.md`](docs/protocol-state.md)
- [`docs/fixtures.md`](docs/fixtures.md)
- [`docs/replay-primitives.md`](docs/replay-primitives.md)
- [`docs/public-api.md`](docs/public-api.md)
- [`docs/stability-policy.md`](docs/stability-policy.md)
- [`docs/capabilities.md`](docs/capabilities.md)
- [`docs/release-checklist.md`](docs/release-checklist.md)

---

## License

MIT. See [LICENSE](LICENSE).
