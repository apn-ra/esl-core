# Changelog

All notable changes to `apntalk/esl-core` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/).
Before `1.0.0`, minor versions may include breaking changes to provisional surfaces.
See [`docs/stability-policy.md`](docs/stability-policy.md) for the full policy.

---

## [Unreleased]

Release preparation is in progress for the next small pre-`1.0.0` tag. The current scope remains the typed ESL protocol substrate already present in the repository; this changelog intentionally avoids implying `1.0.0` stability or runtime completeness.

### Added

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

### Added (tooling-only, not public API)

- `tools/smoke/live_freeswitch_call_flow_validate.php` and `tools/smoke/freeswitch/apn-esl-core-smoke.xml` — non-public live validation tooling for a reversible loopback/tone-stream call flow that can produce `CHANNEL_BRIDGE`, `CHANNEL_UNBRIDGE`, `PLAYBACK_START`, and `PLAYBACK_STOP` frames for quarantined capture. The helper now performs a controlled peer-leg teardown after observing `CHANNEL_BRIDGE` so the loopback flow reliably emits `CHANNEL_UNBRIDGE` during the observation window.
- `tools/smoke/captures/README.md` — updated to include current smoke capture sources and controlled call-flow fixture-candidate guidance.

### Clarified

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
