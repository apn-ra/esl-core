# Stability Policy

## Versioning

This package follows [Semantic Versioning](https://semver.org/).

- **MAJOR** version bump: breaking changes to the public API
- **MINOR** version bump: new features, possibly breaking changes to provisional/internal surfaces
- **PATCH** version bump: bug fixes, no API changes

## Pre-1.0 behavior

Before `1.0.0`, the following rules apply:

- Minor version bumps (`0.x.0`) may introduce breaking changes to **internal** and **provisional** surfaces without deprecation notice.
- Minor version bumps may still adjust documented public surfaces when fixture-backed protocol behavior or boundary discipline requires it, but those changes must be called out explicitly in `CHANGELOG.md`.
- Patch versions are strictly backwards-compatible bug fixes.

## 1.0.0 release criteria

`1.0.0` will be tagged only when ALL of the following conditions are met:

1. Fixture coverage is comprehensive for: framing, parsing, classification, typed commands/replies, normalized event decoding, replay envelope shape.
2. The public interface signatures have been validated against real FreeSWITCH captures.
3. The replay envelope shape is stable and documented.
4. Upper-layer packages (`apntalk/esl-react`, `apntalk/laravel-freeswitch-esl`) have been exercised against the core.
5. The `CHANGELOG.md` accurately reflects all breaking changes since `0.1.0`.

## What "stable" means in this package

Stable means: **behavioral guarantees backed by fixtures**.

A stable interface in this package is not merely "the method signature won't change." It means the behavior under protocol inputs is deterministic, tested, and documented.

Stability is earned incrementally:

| Surface | Stability status |
|---|---|
| `Contracts\*` interfaces | Provisional until fixture-validated |
| `Commands\*` serialization | Stable after Phase 5 |
| `Inbound\InboundPipeline`, `DecodedInboundMessage`, `InboundMessageType` | Stable for the currently supported inbound byte-stream → typed message path; `InboundPipeline::withDefaults()` is the preferred stable construction path |
| `Contracts\InboundConnectionFactoryInterface`, `Inbound\PreparedInboundConnection`, `Inbound\InboundConnectionFactory` | Stable as the supported accepted-stream/bootstrap seam for one inbound connection |
| `Replies\*` parsing | Stable after Phase 5 |
| `Events\NormalizedEvent` | Stable for current substrate invariants; format coverage is stable for `text/event-plain` / `text/event-json` and provisional for `text/event-xml` |
| `Events\BridgeEvent`, `Events\PlaybackEvent` | Stable as selective typed event families |
| `Capability::EventXmlDecoding` / XML normalized event decoding | Provisional pending broader evidence than the current constructed-fixture corpus |
| `Correlation\*` metadata primitives | Stable for the current protocol substrate scope |
| `Replay\*` envelope shape | Provisional until Phase 8 complete |
| `TransportInterface`, `Contracts\TransportFactoryInterface`, `Transport\InMemoryTransport`, `Transport\SocketEndpoint`, `Transport\SocketTransportFactory` | Stable as the minimal transport boundary and supported transport-construction seam for testing, endpoint-based connect, and wrapping accepted PHP stream resources |
| `Internal\Transport\StreamSocketTransport` | Internal-only smoke-path support; deliberately outside the public API boundary |
| Concrete inbound parser/classifier implementations (`Parsing\*`, `Internal\Classification\*`) | Provisional and intentionally outside the stable public API boundary; upper layers should not treat them as the primary ingress contract |
| `Internal\*` | Permanently unstable — not covered by SemVer |

## Adding new protocol fixtures

New ESL behavior must not be merged to public APIs without:

1. A fixture demonstrating the real protocol behavior
2. A test that exercises the fixture through the relevant layer
3. A CHANGELOG entry if it changes observable behavior

## Adding new public types

Before adding a new type to a public namespace:

1. Verify the protocol behavior is grounded in fixtures or documentation
2. Confirm it does not duplicate an existing type
3. Mark it provisional in its docblock if the signature may need revision
4. Add it to this document's stability table above
