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
| `Contracts\InboundPipelineInterface`, `Contracts\InboundConnectionFactoryInterface`, `Contracts\TransportFactoryInterface` | Stable for the currently documented ingress/bootstrap/transport seams |
| `Contracts\FrameSerializerInterface` | Stable as an advanced serializer contract, but `CommandInterface::serialize()` remains the preferred command-output path and `Serialization\CommandSerializer` remains internal |
| Low-level ingress composition contracts (`FrameParserInterface`, `EventParserInterface`, `InboundMessageClassifierInterface`) | Provisional and intended for advanced fixture-backed composition, not the default downstream ingress path; `InboundMessageClassifierInterface` still returns the current internal carrier for compatibility, so treat `ClassifiedMessageInterface` plus `ReplyFactory::fromClassification()` as the staged public bridge rather than a hardened producer-side contract |
| `Commands\*` serialization | Stable after Phase 5 |
| `Inbound\InboundPipeline`, `DecodedInboundMessage`, `InboundMessageType` | Stable for the currently supported inbound byte-stream → typed message path; `InboundPipeline::withDefaults()` is the preferred stable construction path, while direct constructor injection remains an advanced public seam without an active soft deprecation |
| `Contracts\InboundConnectionFactoryInterface`, `Inbound\PreparedInboundConnection`, `Inbound\InboundConnectionFactory` | Stable as the supported accepted-stream/bootstrap seam for one inbound connection |
| `Contracts\ClassifiedMessageInterface` | Stable as the additive public read-only contract for advanced classified-message access; the current internal carrier implements it, while `InboundMessageClassifierInterface` itself remains provisional |
| `Replies\*` parsing | Stable after Phase 5; `ReplyFactory::fromFrame()` is the explicit advanced frame-owned reply bridge, `fromClassification()` is the additive public classified-message bridge, while `fromClassified()` remains a lower-level classified-message path and `InboundPipeline` is the preferred upper-layer decode seam |
| `Events\NormalizedEvent` | Stable for current substrate invariants; format coverage is stable for `text/event-plain` / `text/event-json` and provisional for `text/event-xml` |
| `Contracts\ProvidesNormalizedSubstrateInterface` | Stable as an additive explicit substrate-access contract for `NormalizedEvent` and the built-in typed event wrappers; preferred byte-ingress access still comes from `DecodedInboundMessage::normalizedEvent()` |
| `Events\EventFactory`, `Events\EventClassifier` | Stable as advanced event-composition bridges for callers that already own a frame or normalized event, but not the preferred byte-ingress seam |
| `Events\BridgeEvent`, `Events\PlaybackEvent` | Stable as selective typed event families |
| `Capability::EventXmlDecoding` / XML normalized event decoding | Provisional pending broader evidence than the current constructed-fixture corpus |
| `Correlation\*` metadata primitives | Stable for the current protocol substrate scope |
| `Replay\*` envelope shape | Provisional until Phase 8 complete |
| `TransportInterface`, `Contracts\TransportFactoryInterface`, `Transport\InMemoryTransport`, `Transport\SocketEndpoint`, `Transport\SocketTransportFactory` | Stable as the minimal transport boundary and supported transport-construction seam for testing, endpoint-based connect, and wrapping accepted PHP stream resources |
| `Protocol\Frame`, `Protocol\HeaderBag` | Stable substrate value objects because public reply/event contracts expose them directly |
| `Internal\Transport\StreamSocketTransport` | Internal-only smoke-path support; deliberately outside the public API boundary |
| Concrete inbound parser/classifier implementations (`Parsing\*`, `Internal\Classification\*`) | Provisional and intentionally outside the stable public API boundary; upper layers should not treat them as the primary ingress contract |
| Remaining `Protocol\*` and `Internal\*` | Permanently unstable — not covered by SemVer |

The support level in this table does not mean every composition seam is equally preferred. A surface may be stable while still being an advanced public seam rather than the default downstream integration path. In this release line, that distinction is intentional: `InboundPipeline::withDefaults()`, `SocketTransportFactory`, and `InboundConnectionFactory` are the preferred stable seams, while lower-level parser/classifier/factory composition remains public but more advanced.

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
