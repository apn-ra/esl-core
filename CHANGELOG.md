# Changelog

All notable changes to `apntalk/esl-core` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/).
Before `1.0.0`, minor versions may include breaking changes to provisional surfaces.
See [`docs/stability-policy.md`](docs/stability-policy.md) for the full policy.

---

## [Unreleased] — 0.1.0 baseline

### Added

**Integration coverage**
- `tests/Integration/InMemoryTransportPipelineTest` — end-to-end pipeline coverage for auth replies, api replies, bgapi + `BACKGROUND_JOB`, unsolicited events, and unknown-event degradation using `InMemoryTransport`
- `tests/Contract/Replay/ReplayEnvelopeFactoryTest` — replay-envelope preservation of session/observation/correlation metadata
- `tests/Contract/Exceptions/ErrorTaxonomyTest` — distinct malformed/truncated/unsupported/reply-shape/transport failure coverage

### Changed

**Replay-envelope tightening**
- `ReplayEnvelope` now preserves `protocolFacts` separately from `derivedMetadata`
- `ReplayEnvelopeFactory` now supports `fromReplyEnvelope()` and `fromEventEnvelope()` so replay capture can preserve `ConnectionSessionId`, `ObservationSequence`, `observedAtMicros`, protocol sequence, protocol-native identifiers, and derived correlation metadata already assigned by `CorrelationContext`
- Direct `fromReply()` / `fromEvent()` capture remains available for narrower use cases where correlation metadata has not been attached yet

**Error taxonomy hardening**
- Added `MalformedFrameException`, `TruncatedFrameException`, `UnsupportedContentTypeException`, `ReplayException`, and `ReplayConsistencyException`
- `FrameParser::finish()` now distinguishes buffered partial input from malformed input at end-of-input
- `EventParser` now throws `UnsupportedContentTypeException` for unsupported event content types and `MalformedFrameException` for malformed event bodies
- Typed reply constructors now throw `UnexpectedReplyException` when the supplied frame does not match the required protocol shape

### Added

**Repository foundation (Phase 1)**
- `composer.json` — package definition, PSR-4 autoloading, dev tooling
- `phpunit.xml` — PHPUnit 11 configuration with Unit/Contract/Integration suites
- `.phpstan.neon` — PHPStan level 8 static analysis config
- `.php-cs-fixer.php` — PER-CS 2.0 coding standard config
- `.github/workflows/ci.yml` — CI: test matrix (PHP 8.1–8.3), PHPStan, CS check
- `docs/public-api.md` — public API namespace boundary policy
- `docs/stability-policy.md` — SemVer and 1.0.0 release criteria

**Fixture corpus (Phase 2)**
- `tests/Fixtures/EslFixtureBuilder` — programmatic builder for all fixture types
- `tests/Fixtures/FixtureLoader` — file-based fixture loader with intent documentation
- `docs/fixtures.md` — fixture naming scheme, provenance policy, validation rules

**Wire model and codec (Phase 3)**
- `Protocol\HeaderBag` — immutable, case-insensitive header collection; raw values; `with()` mutation
- `Protocol\Frame` — wire-level frame (headers + raw body); no semantic interpretation
- `Protocol\MessageType` — backed enum for ESL content-types with `fromContentType()` degradation
- `Parsing\FrameParser` — incremental, partial-read-safe ESL frame parser (state machine)
- `Serialization\CommandSerializer` — `FrameSerializerInterface` adapter over command `serialize()`
- `Exceptions\ProtocolException`, `ParseException`, `SerializationException`, `TransportException`, `ClassificationException`, `UnexpectedReplyException`
- All `Contracts\*` interfaces: `FrameParserInterface`, `FrameSerializerInterface`, `CommandInterface`, `ReplyInterface`, `EventInterface`, `EventParserInterface`, `EventFactoryInterface`, `InboundMessageClassifierInterface`, `TransportInterface`, `ReplayEnvelopeInterface`, `ReplayCaptureSinkInterface`, `ReconstructionHookInterface`, `CapabilityMapInterface`

**Protocol classification (Phase 4)**
- `Internal\Classification\InboundMessageCategory` — semantic category enum
- `Internal\Classification\ClassifiedInboundMessage` — frame + category + message type
- `Internal\Classification\InboundMessageClassifier` — deterministic frame classifier

**Typed commands and replies (Phase 5)**
- `Commands\AuthCommand`, `ApiCommand`, `BgapiCommand`, `EventSubscriptionCommand`, `FilterCommand`, `NoEventsCommand`, `ExitCommand`, `RawCommand`, `EventFormat`
- `Replies\AuthAcceptedReply`, `CommandReply`, `ErrorReply`, `BgapiAcceptedReply`, `ApiReply`, `UnknownReply`, `ReplyFactory`

**Normalized event model (Phase 6)**
- `Parsing\EventParser` — parses `text/event-plain` frames into `NormalizedEvent`; splits event headers/body at `\n\n`
- `Events\NormalizedEvent` — URL-decoded header access + raw body + frame reference
- `Events\RawEvent` — safe degradation wrapper for unknown event names
- `Events\BackgroundJobEvent`, `ChannelLifecycleEvent`, `HangupEvent`, `CustomEvent`
- `Events\EventClassifier` — maps event names to typed subclasses; degrades to `RawEvent`
- `Events\EventFactory` — combines `EventParser` + `EventClassifier` in one call

**Replay-safe substrate (Phase 8)**
- `Replay\ReplayEnvelope` — deterministic capture envelope with session/sequence/payload metadata
- `Replay\ReplayEnvelopeFactory` — produces envelopes from replies and events
- `Replay\ReplayCapturePolicy` — configurable capture filter (all/replies/events/exclude list)
- `docs/replay-primitives.md`

**Minimal transport (Phase 10)**
- `Transport\InMemoryTransport` — test double with `enqueueInbound()` / `drainOutbound()`

**Capabilities (Phase 9)**
- `Capabilities\Capability` — named capability enum
- `Capabilities\FeatureSupportLevel` — stable / provisional / unsupported
- `Capabilities\CapabilityMap` — runtime-inspectable capability declarations
- `docs/capabilities.md`

**Correlation and session primitives (Phase 7)**
- `Correlation\ConnectionSessionId` — immutable UUID v4 session identity; one per connection; not a FreeSWITCH protocol type
- `Correlation\ObservationSequence` — deterministic 1-based observation ordering within a session; distinct from `Event-Sequence`
- `Correlation\JobCorrelation` — links `BgapiAcceptedReply` to a later `BackgroundJobEvent` by Job-UUID; factory from both ends
- `Correlation\ChannelCorrelation` — channel-oriented correlation; partial correlation (missing fields) modeled explicitly and honestly
- `Correlation\MessageMetadata` — composite metadata per protocol object: session ID, observation sequence, microsecond timestamp, job/channel correlation, protocol sequence
- `Correlation\CorrelationContext` — stateful per-session factory; assigns monotonic sequences; extracts job/channel correlation by type-safe inspection
- `Correlation\EventEnvelope` — typed event + `MessageMetadata` wrapper with convenience pass-throughs
- `Correlation\ReplyEnvelope` — typed reply + `MessageMetadata` wrapper with convenience pass-throughs
- `Replay\ReplayEnvelopeFactory::withSession(ConnectionSessionId)` — static factory binding a replay factory to a session identity shared with `CorrelationContext`
- `docs/correlation.md` — identifier taxonomy, all types with usage examples, typical session pattern, CorrelationContext vs ReplayEnvelope comparison

**Documentation**
- `README.md`, `docs/architecture.md`, `docs/protocol-model.md`, `docs/protocol-state.md`, `docs/fixtures.md`, `docs/replay-primitives.md`, `docs/public-api.md`, `docs/stability-policy.md`, `docs/capabilities.md`, `docs/correlation.md`

### Test coverage
- 232 tests, 552 assertions — all passing
- `tests/Unit/Protocol/HeaderBagTest` — 18 tests
- `tests/Unit/Protocol/FrameTest` — 8 tests
- `tests/Contract/Parsing/FrameParserTest` — 17 tests
- `tests/Contract/Parsing/FrameParserPartialTest` — 8 tests
- `tests/Contract/Parsing/FrameParserMalformedTest` — 7 tests
- `tests/Contract/Classification/InboundMessageClassifierTest` — 16 tests
- `tests/Unit/Commands/CommandSerializationTest` — 22 tests
- `tests/Contract/Replies/ReplyFactoryTest` — 20 tests
- `tests/Contract/Events/EventParserTest` — 31 tests
- `tests/Unit/Correlation/ConnectionSessionIdTest` — 8 tests
- `tests/Unit/Correlation/ObservationSequenceTest` — 16 tests
- `tests/Unit/Correlation/JobCorrelationTest` — 8 tests
- `tests/Unit/Correlation/ChannelCorrelationTest` — 13 tests
- `tests/Contract/Correlation/CorrelationContextTest` — 26 tests
- `tests/Contract/Replay/ReplayEnvelopeFactoryTest` — 3 tests
- `tests/Contract/Exceptions/ErrorTaxonomyTest` — 6 tests
- `tests/Integration/InMemoryTransportPipelineTest` — 5 tests

### Not yet implemented
- Phase 9: CapabilityMap tests
- `text/event-json` and `text/event-xml` event parsing (EventParser covers plain only)
- `CHANNEL_BRIDGE`/`CHANNEL_UNBRIDGE` → `BridgeEvent`, `PLAYBACK_START`/`PLAYBACK_STOP` → `PlaybackEvent`
