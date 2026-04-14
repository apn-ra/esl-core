# apntalk/esl-core

**Framework-agnostic, transport-neutral, typed FreeSWITCH ESL protocol library for PHP with replay-safe protocol primitives.**

---

## What this package is

`apntalk/esl-core` is a **protocol substrate** for FreeSWITCH Event Socket Layer (ESL) clients.

It provides:

- A truthful ESL wire model (framing, parsing, serialization)
- Deterministic message classification (auth, command replies, events, bgapi)
- Typed command and reply objects
- A normalized event model with safe degradation for unknown event types
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

This package follows [SemVer](https://semver.org/). Before `1.0.0`, minor versions may introduce breaking changes in provisional (internal) surfaces. The public API boundary is documented in [`docs/public-api.md`](docs/public-api.md).

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

## Quick example

```php
use Apntalk\EslCore\Parsing\FrameParser;
use Apntalk\EslCore\Commands\AuthCommand;
use Apntalk\EslCore\Internal\Classification\InboundMessageClassifier;
use Apntalk\EslCore\Replies\ReplyFactory;

// Parse incoming bytes
$parser = new FrameParser();
$parser->feed($incomingBytes);

foreach ($parser->drain() as $frame) {
    $classified = (new InboundMessageClassifier())->classify($frame);
    $reply = (new ReplyFactory())->fromClassified($classified);
    // $reply is typed: AuthAcceptedReply, CommandReply, ErrorReply, etc.
}

// Serialize outgoing command
$command = new AuthCommand('ClueCon');
$wireBytes = $command->serialize(); // "auth ClueCon\n\n"
```

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

---

## License

MIT. See [LICENSE](LICENSE).
