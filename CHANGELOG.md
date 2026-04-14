# Changelog

All notable changes to `apntalk/esl-core` are documented here.

This project adheres to [Semantic Versioning](https://semver.org/).
Before `1.0.0`, minor versions may include breaking changes to provisional surfaces.
See [`docs/stability-policy.md`](docs/stability-policy.md) for the full policy.

---

## [Unreleased]

Release preparation is in progress for the next small pre-`1.0.0` tag. The current scope is the typed ESL protocol substrate already present in the repository; this changelog intentionally avoids implying `1.0.0` stability or runtime completeness.

## [0.2.0] - Draft

### Highlights

- Typed FreeSWITCH ESL protocol substrate for PHP covering framing, deterministic classification, typed commands, typed replies, normalized events, correlation metadata, replay-safe envelopes, and minimal in-memory transport
- Selective typed event families for bridge and playback events, alongside background job, channel lifecycle, hangup, and custom event handling
- `text/event-json` normalization support through the existing parser/classifier/factory path
- Explicit failure taxonomy covering malformed input, truncated input, unsupported content types, unexpected reply shapes, transport failures, and replay consistency assumptions
- Capability verification, end-to-end `InMemoryTransport` integration coverage, and clean PHPUnit/PHPStan verification
- Release-facing docs clarifying package boundaries, provisional surfaces, and deferred work

### Verification

- PHPUnit, PHPStan, Composer metadata validation, and coding-standard checks are part of release readiness for this draft release.
- Capability declarations are verified against the implemented support surfaces.

### Deferred for a later pre-`1.0.0` release

- `text/event-xml`
- framework/runtime integrations
- transport expansion beyond `InMemoryTransport`
- replay storage, scheduling, and orchestration
