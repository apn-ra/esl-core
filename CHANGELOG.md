# Changelog

All notable changes to `apntalk/esl-core` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/).
Before `1.0.0`, minor versions may include breaking changes to provisional surfaces.
See [`docs/stability-policy.md`](docs/stability-policy.md) for the full policy.

---

## [Unreleased]

Release preparation is in progress for the next small pre-`1.0.0` tag. The current scope remains the typed ESL protocol substrate already present in the repository; this changelog intentionally avoids implying `1.0.0` stability or runtime completeness.

### Added

- `tests/Integration/SocketTransportFactoryPipelineTest` — a public-path integration proof for the supported `SocketTransportFactory::connect()` + `InboundPipeline::withDefaults()` composition over a real local TCP socket, including fragmented inbound decode and typed bgapi acceptance/completion assertions.
- `tests/Contract/Events/EventFactoryTest` — focused contract coverage for the public-but-advanced `EventFactory` bridge, mirroring the reply-side tiering proof without promoting it to the preferred raw-byte ingress path.
- `InboundPipeline::withDefaults()` — a stable named construction path for the supported ingress facade, reducing the need for downstream packages to couple to concrete parser/classifier collaborator types through the public constructor.
- `src/Contracts/InboundConnectionFactoryInterface.php`, `src/Inbound/PreparedInboundConnection.php`, and `src/Inbound/InboundConnectionFactory.php` — a stable public accepted-stream/bootstrap seam for downstream packages that need to prepare one inbound connection as transport + pipeline + correlation context without ad hoc assembly.
- `src/Contracts/TransportFactoryInterface.php`, `src/Transport/SocketEndpoint.php`, and `src/Transport/SocketTransportFactory.php` — a stable public transport-construction seam for downstream packages that need to connect from endpoint/config inputs or wrap an already accepted PHP stream without depending on `Internal\Transport\StreamSocketTransport`.
- Provisional `text/event-xml` normalization support through `EventParser` and the supported `InboundPipeline` facade, backed by constructed fixture coverage and safe typed/fallback behavior.
- Additional parser and internal stream/socket smoke coverage for coalesced multi-frame reads, delayed body delivery, delayed bgapi completion, and mid-frame connection loss.
- `src/Inbound/InboundPipeline`, `src/Inbound/DecodedInboundMessage`, `src/Inbound/InboundMessageType`, and `src/Contracts/InboundPipelineInterface` — a stable public inbound decoding facade for raw byte ingestion. Upper layers can now consume auth requests, typed replies, typed events, normalized events, disconnect notices, and safe `RawEvent` / `UnknownReply` fallbacks without composing the provisional parser/classifier internals directly.
- `src/Internal/Transport/StreamSocketTransport` and `tests/Integration/StreamSocketTransportPipelineTest.php` — bounded internal stream/socket smoke coverage proving the wire model against real PHP stream-socket behavior without widening the supported transport API.
- `tests/Fixtures/sequences/bgapi-acceptance-and-completion.esl` and `tests/Contract/Inbound/InboundPipelineTest.php` — fixture-backed bgapi acceptance/completion sequence coverage through the new public facade, including replay/correlation assertions for the sensitive Job-UUID path.

- `docs/live-fixture-provenance.md` — central provenance record for curated live fixtures promoted from the controlled loopback call-flow captures. Ties each promoted fixture to its exact quarantined source capture, capture mode, promotion reason, and contract test coverage.
- `tests/Fixtures/live/events/background-job-no-route-destination-plain.esl` — curated live fixture for a `BACKGROUND_JOB` event carrying a `-ERR NO_ROUTE_DESTINATION` failure body. Promoted byte-for-byte from a call-flow validation capture (session `20260414T062140Z`). Documents the observed failure path when FreeSWITCH accepted a bgapi `originate` command but the target dialplan route was not installed on the PBX.
- `tests/Contract/Events/LiveBackgroundJobFailureFixtureTest` — 15 tests pinning the parse, classify, normalize, and typed-event behavior for the above fixture. Verifies `isSuccess()` is false, the `-ERR NO_ROUTE_DESTINATION\n` body is preserved exactly, and correlation identifiers (`Job-UUID`, `Core-UUID`, `Event-Sequence`) round-trip correctly. No new typed-event expectations; bridge/playback event coverage is deferred pending a successful PBX rerun.
- Curated live JSON call-flow fixtures promoted from a controlled loopback/tone-stream smoke run: `channel-bridge-loopback-json.esl`, `channel-unbridge-loopback-json.esl`, `playback-start-tone-stream-json.esl`, `playback-stop-tone-stream-json.esl`, and `background-job-originate-ok-json.esl`.
- `tests/Contract/Events/LiveCallFlowJsonFixtureTest` — focused contract coverage proving the promoted `text/event-json` frames parse, classify, normalize, and decode into the current typed families (`BridgeEvent`, `PlaybackEvent`, and `BackgroundJobEvent`) without adding new public event families.
- Curated live plain call-flow fixtures promoted from the matching controlled loopback/tone-stream smoke path: `channel-bridge-loopback-plain.esl`, `channel-unbridge-loopback-plain.esl`, `playback-start-tone-stream-plain.esl`, and `playback-stop-tone-stream-plain.esl`.
- `tests/Contract/Events/LiveCallFlowPlainFixtureTest` — focused contract coverage proving the promoted `text/event-plain` frames parse, classify, URL-decode normalized headers, attach correlation metadata, preserve replay protocol facts, and decode into the current typed families (`BridgeEvent` and `PlaybackEvent`).
- `tests/Contract/Inbound/ConnectSubscribeTest` — 16 tests pinning the public-facade connect-and-subscribe path. Verifies that `AuthCommand`, `EventSubscriptionCommand` (plain/json/named), `FilterCommand`, and `NoEventsCommand` each produce the correct wire bytes when written to `InMemoryTransport`, that the current internal serializer adapter stays consistent with direct `serialize()`, and that the full auth-handshake → subscription sequence (`auth/request` → `AuthCommand` → `AuthAcceptedReply` → `EventSubscriptionCommand` → `CommandReply`) is observable end-to-end through `InboundPipeline` without reaching into provisional internal types. Auth rejection and disconnect notice paths are also pinned.
- Two additional tests in `tests/Contract/Inbound/InboundPipelineTest` pinning the auth-rejection (`-ERR` → `ErrorReply`) and disconnect-notice paths through the `InboundPipeline` public facade — filling the last gap identified by the substrate review pass.
- `tests/Integration/SessionFlowCorrelationTest` — 12 tests closing the `InboundPipeline` + `CorrelationContext` composition seam through a realistic session sequence. Verifies that feeding a concatenated block of five frames (auth-accepted, subscription-accepted, `CHANNEL_CREATE`, `BACKGROUND_JOB`, `CHANNEL_HANGUP`) through `InboundPipeline` and then wrapping each decoded message with `CorrelationContext` produces strictly monotonic observation sequences (1–5), consistent session IDs, channel correlation on channel events, job correlation on background-job events, null correlation on plain replies, and correct FreeSWITCH `Event-Sequence` capture in `MessageMetadata.protocolSequence()`. Also verifies the same flow when bytes are read from `InMemoryTransport` and that the outbound command-write path composes cleanly in the same session. No implementation code changes were required.

### Clarified

- Typed-event normalized-substrate access is now expressed more explicitly: the additive public contract `Contracts\ProvidesNormalizedSubstrateInterface` exposes `normalized(): NormalizedEvent` on `NormalizedEvent` and the built-in typed event wrappers. `CorrelationContext` and `ReplayEnvelopeFactory` now prefer that explicit contract and only retain reflection as a compatibility fallback for legacy/property-based event wrappers.
- `ReplyFactory::fromFrame()` now provides an explicit advanced reply-side seam for callers that already own a parsed `Frame` but do not want to pass the internal `ClassifiedInboundMessage` carrier around directly. The older `fromClassified()` path remains in place for lower-level fixture-backed composition.
- `Contracts\ClassifiedMessageInterface` now provides an additive public read-only contract for advanced classified-message access. The current internal classified carrier implements it, and `ReplyFactory::fromClassification()` consumes it directly while preserving the older classifier and `fromClassified()` signatures for compatibility.
- Downstream integration guidance now centers the supported seams more explicitly: `SocketTransportFactory` for connection construction, `InboundConnectionFactory::prepareAcceptedStream()` for accepted-stream bootstrap, and `InboundPipeline::withDefaults()` for preferred byte ingress. The docs now separate those preferred seams from advanced public composition bridges such as `ReplyFactory`, `EventFactory`, and lower-level parser/classifier contracts.
- Maintainer verification ergonomics are now aligned around Composer entrypoints. Dedicated `composer unit`, `composer contract`, and `composer integration` scripts were added for narrow suite runs, the release checklist now treats `composer check` as the main local gate with `composer smoke` as an optional fast sanity pass, and CI now routes through the same Composer script surface to reduce drift.
- Later-phase hardening notes now document, rather than redesign, the current posture around normalized-event substrate access and public-but-advanced classified/parser/factory seams.
- `EventFactory` and `EventClassifier` remain public as advanced event-composition bridges for callers that already own a `Frame` or `NormalizedEvent`, but they are not the preferred byte-ingress seam. The preferred upper-layer raw-byte path remains `InboundPipeline::withDefaults()`.
- `InboundPipeline::withDefaults()` is now the preferred public ingress construction path. Direct constructor collaborator injection remains available for advanced composition, but it is no longer the recommended downstream entry point.
- The supported public inbound story now includes accepted-stream bootstrap: `InboundConnectionFactoryInterface`, `PreparedInboundConnection`, and `InboundConnectionFactory` are public, while listener ownership, read loops, and session supervision remain outside core.
- The supported public transport boundary now includes a construction seam: `TransportFactoryInterface`, `SocketEndpoint`, and `SocketTransportFactory` are public, while `Internal\Transport\StreamSocketTransport` remains an implementation detail behind that seam.
- `ChannelCorrelation` boundary extended in docblock and `docs/correlation.md`: caller ID name/number and channel state are intentionally excluded. Caller ID is display metadata accessed at handler time via `NormalizedEvent` or the typed event. Channel state is a transient snapshot that changes throughout the call and must be maintained in an upper-layer state machine keyed by `uniqueId()`, not captured in the correlation primitive. No code changes were required.

### Added (tooling-only, not public API)

- `tools/smoke/live_freeswitch_call_flow_validate.php` and `tools/smoke/freeswitch/apn-esl-core-smoke.xml` — non-public live validation tooling for a reversible loopback/tone-stream call flow that can produce `CHANNEL_BRIDGE`, `CHANNEL_UNBRIDGE`, `PLAYBACK_START`, and `PLAYBACK_STOP` frames for quarantined capture. The helper now performs a controlled peer-leg teardown after observing `CHANNEL_BRIDGE` so the loopback flow reliably emits `CHANNEL_UNBRIDGE` during the observation window.
- `tools/smoke/captures/README.md` — updated to include current smoke capture sources and controlled call-flow fixture-candidate guidance.

### Clarified

- `InboundPipeline` is now the clearly dominant supported upper-layer ingress contract; lower-level parser/classifier contracts remain available but intentionally provisional.
- `InboundMessageClassifierInterface` remains unchanged in this release line even though its return type still names the current internal carrier. The staged public bridge for advanced classified-message work is `Contracts\ClassifiedMessageInterface` plus `ReplyFactory::fromClassification()`, not a producer-side classifier signature change.
- `NormalizedEvent` remains a protocol-substrate object rather than an application aggregate: it now exposes explicit source-content-type and URL-encoding invariants while correlation/replay/runtime state stays in separate layers.
- The supported ingress surface for upper layers is now `Inbound\InboundPipeline`; concrete parser/classifier implementations remain intentionally provisional even though they continue to exist in-repo for low-level testing.
- Current bridge/playback typed-event coverage is live-backed in both `text/event-plain` and `text/event-json`, but that evidence comes from curated fixtures and non-public smoke tooling rather than from any supported runtime or transport integration surface.

## [0.2.1] - 2026-04-14

### Clarified

- Documented the current `ApiReply::isSuccess()` contract more explicitly: it is a `+OK` body-prefix check, not a generic indicator that every `api/response` body represents success.
- Promoted authenticated live auth-accept and `api status` captures into test fixtures so reply behavior is pinned by real wire samples.
- Aligned the public API docs so the minimal `Transport` boundary is documented consistently as public, while clarifying that the current concrete inbound parse/classify path remains a provisional early-adopter integration surface.

### Fixed

- Restored a clean release-check path by removing an unused import in the chaos integration test so `composer check` passes again.

### Added

- A deterministic chaos test path covering fragmented delivery, mixed reply/event streams, safe degradation for unknown inputs, explicit malformed/truncated failures, and correlation/replay consistency under noisy session traffic.

## [0.2.0] - Draft

### Highlights

- Typed FreeSWITCH ESL protocol substrate for PHP covering framing, deterministic classification, typed commands, typed replies, normalized events, correlation metadata, replay-safe envelopes, and minimal in-memory transport
- Selective typed event families for bridge and playback events, alongside background job, channel lifecycle, hangup, and custom event handling
- `text/event-json` normalization support through the existing parser/classifier/factory path
- Explicit failure taxonomy covering malformed input, truncated input, unsupported content types, unexpected reply shapes, transport failures, and replay consistency assumptions
- Capability verification, end-to-end `InMemoryTransport` integration coverage, and clean PHPUnit/PHPStan verification
- Release-facing docs clarifying package boundaries, provisional surfaces, and deferred work

### Verification

- Added a narrow smoke-test path for the current happy-path command/reply and async event substrate wiring
- PHPUnit, PHPStan, Composer metadata validation, and coding-standard checks are part of release readiness for this draft release.
- Capability declarations are verified against the implemented support surfaces.

### Deferred for a later pre-`1.0.0` release

- `text/event-xml`
- framework/runtime integrations
- transport expansion beyond `InMemoryTransport`
- replay storage, scheduling, and orchestration
