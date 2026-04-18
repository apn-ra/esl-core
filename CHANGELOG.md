# Changelog

All notable changes to `apntalk/esl-core` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/).
Before `1.0.0`, minor versions may include breaking changes to provisional surfaces.
See [`docs/stability-policy.md`](docs/stability-policy.md) for the full policy.

---

## [Unreleased]

---

## [0.2.11] - 2026-04-18

### Added

- Added `Contracts\CompletableFrameParserInterface` and
  `InboundPipeline::withContracts(...)` as the advanced public composition path
  for parser/classifier replacement without depending on concrete internals.

### Changed

- `InboundMessageClassifierInterface::classify()` now returns the public
  `ClassifiedMessageInterface` result contract, allowing downstream
  classifiers, decorators, mocks, and adapters to avoid importing the internal
  classifier carrier. Existing implementations that return the current concrete
  internal carrier remain compatible because it implements the public contract.

### Clarified

- Release and provenance docs now align with the actual live-capture retention
  policy: quarantined `tools/smoke/captures/` files are operator artifacts and
  are not expected to exist in a fresh checkout.
- Advanced classifier and inbound-pipeline customization docs now distinguish
  the new public-contract composition path from lower-level concrete escape
  hatches, while keeping both subordinate to the preferred downstream ingress
  path.
- The public transport write contract now explicitly assumes a blocking or
  runtime-managed writable stream; core does not provide async would-block
  buffering or retry scheduling in this release line.

---

## [0.2.10] - 2026-04-18

### Fixed

- Restored LF-only malformed and partial parser fixtures, and pinned raw fixture line endings in git so malformed-input coverage stays byte-stable across platforms.
- `EventParser` now rejects `text/event-plain` payloads that omit the required inner header terminator instead of accepting them as header-only events.
- `HeaderBag::toFlatArray()` now preserves true flat insertion order for interleaved duplicate headers, so replay/export payloads keep their documented header ordering.
- The default PHPUnit no-coverage path no longer fails solely because coverage reporting was configured without a driver.

### Clarified

- Release-facing docs now reflect the actual checked-in fixture layout and provenance posture for promoted live fixtures.
- XML event decoding now declares its `ext-dom` requirement explicitly in package metadata and capability docs.
- The current release-line posture around built-in typed-event normalized substrate access and real-stream blocking semantics is now documented more explicitly.

---

## [0.2.9] - 2026-04-18

### Fixed

- `ReplayEnvelopeFactory` now preserves reply body bytes in `ReplayEnvelope.rawPayload` by exporting a deterministic frame-shaped reply payload instead of silently dropping bodies such as `api/response` output during replay capture.
- Typed command constructors now reject carriage return and newline characters in user-provided command segments so the default typed command surface cannot accidentally serialize multi-command or framing-breaking ESL payloads. `RawCommand` remains the explicit raw escape hatch.
- Correlation and replay substrate extraction now rely only on `ProvidesNormalizedSubstrateInterface` instead of a reflection-based public-property fallback, so custom typed events must opt in explicitly to richer normalized-substrate behavior.
- `ClassifiedMessageInterface` no longer advertises a distinct auth-rejected outcome that the core classifier does not emit; auth `-ERR` remains the truthful `CommandError` classifier result, with auth-failure interpretation left to upper-layer session context.
- `HeaderBag::with()` now enforces the same header-name validity invariant as `HeaderBag::fromHeaderBlock()`, rejecting empty or surrounding-whitespace header names instead of allowing states the parser would reject.
- `UnknownReply::isSuccess()` is now documented explicitly as a conservative "not known-success" signal for unsupported or degraded reply shapes, rather than an implied typed protocol-failure classification.
- JSON/XML event parser coverage now explicitly proves header values containing `": "` survive normalization intact, guarding the header-block reconstruction path against subtle delimiter regressions.
- `FrameParser` buffering policy is now stated explicitly: digit-only `Content-Length` values are buffered without a built-in size cap, and memory/body-size limits remain an embedding transport/runtime responsibility rather than a parser-owned policy.

---

## [0.2.8] - 2026-04-18

### Fixed

- Smoke helper entrypoints under `tools/smoke/` now resolve Composer autoload from either the repo root or a normal Composer-installed dependency tree, so downstream packages can execute the upstream helpers directly without local proxy wrappers or vendor-tree hacks.

---

## [0.2.7] - 2026-04-18

### Corrected

- The published `v0.2.7` tag and installable artifact were cut from the earlier `Finalize release boundary docs` commit and therefore did not include the smoke-helper bootstrap resolver files or entrypoint updates described in the release notes. Consumers needing the smoke-helper bootstrap fix should use `v0.2.8` or later.

---

## [0.2.6] - 2026-04-18

This pre-`1.0.0` patch release closes the bounded malformed-frame and protocol-side fixture/harness hardening pass. The package remains a typed ESL protocol substrate; this release does not add runtime supervision, reconnect/backoff ownership, framework integration, or replay execution.

### Added

- Deterministic malformed/partial parser fixtures under `tests/Fixtures/malformed/` and `tests/Fixtures/partial/`, with focused parser, event-parser, and public-facade tests proving invalid/whitespace-padded header names, invalid `Content-Length`, missing header separators, missing header terminators, truncated outer bodies, and mismatched inner event bodies.
- Public-facade coverage for representative malformed and partial fixture inputs through `InboundPipeline`.

### Clarified

- Fixture documentation now distinguishes raw parser input from sidecar provenance notes. Malformed and partial fixtures should keep exact bytes in the fixture file and record provenance in README/provenance surfaces or tests rather than adding comments that would alter parser input.
- `docs/downstream-integration.md` and `README.md` now separate runtime/reconnect ownership (`apntalk/esl-react`), framework/service integration ownership (`apntalk/laravel-freeswitch-esl`), and replay execution/re-injection ownership (`apntalk/esl-replay`) from this package's protocol substrate responsibilities.

### Fixed

- `HeaderBag::fromHeaderBlock()` now rejects empty, whitespace-only, or whitespace-padded header names as malformed frame input instead of accepting ambiguous header keys.
- `EventParser` now rejects event payloads whose inner `Content-Length` is non-numeric or does not match the normalized event body byte length, instead of silently accepting semantically incomplete event bodies.

## [0.2.1] - 2026-04-14

### Clarified

- Documented the current `ApiReply::isSuccess()` contract more explicitly: it is a `+OK` body-prefix check, not a generic indicator that every `api/response` body represents success.
- Promoted authenticated live auth-accept and `api status` captures into test fixtures so reply behavior is pinned by real wire samples.
- Aligned the public API docs so the minimal `Transport` boundary is documented consistently as public, while clarifying that the current concrete inbound parse/classify path remains a provisional early-adopter integration surface.

### Fixed

- Restored a clean release-check path by removing an unused import in the chaos integration test so `composer check` passes again.

### Added

- A deterministic chaos test path covering fragmented delivery, mixed reply/event streams, safe degradation for unknown inputs, explicit malformed/truncated failures, and correlation/replay consistency under noisy session traffic.

## [0.2.0] - 2026-04-14

### Highlights

- Typed FreeSWITCH ESL protocol substrate for PHP covering framing, deterministic classification, typed commands, typed replies, normalized events, correlation metadata, replay-safe envelopes, and minimal in-memory transport
- Selective typed event families for bridge and playback events, alongside background job, channel lifecycle, hangup, and custom event handling
- `text/event-json` normalization support through the existing parser/classifier/factory path
- Explicit failure taxonomy covering malformed input, truncated input, unsupported content types, unexpected reply shapes, transport failures, and replay consistency assumptions
- Capability verification, end-to-end `InMemoryTransport` integration coverage, and clean PHPUnit/PHPStan verification
- Release-facing docs clarifying package boundaries, provisional surfaces, and deferred work

### Verification

- Added a narrow smoke-test path for the current happy-path command/reply and async event substrate wiring
- PHPUnit, PHPStan, Composer metadata validation, and coding-standard checks were part of the release-readiness gate for this initial pre-`1.0.0` tag.
- Capability declarations are verified against the implemented support surfaces.

### Deferred for a later pre-`1.0.0` release

- `text/event-xml`
- framework/runtime integrations
- transport expansion beyond `InMemoryTransport`
- replay storage, scheduling, and orchestration
