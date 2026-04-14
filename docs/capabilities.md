# Capabilities

This document lists the declared capabilities of `apntalk/esl-core` and their current support levels.

Capabilities are backed by real tests and documentation. A capability is only declared once it is implemented and covered.

## Support levels

| Level | Meaning |
|---|---|
| `stable` | Implemented, fixture-backed, API is stable |
| `provisional` | Implemented but signature or behavior may change before 1.0 |
| `unsupported` | Not yet implemented |

## Declared capabilities

| Capability | Level | Notes |
|---|---|---|
| `auth` | stable | Auth command, auth/request parsing, AuthAcceptedReply, ErrorReply |
| `api-command` | stable | ApiCommand serialization, ApiReply parsing |
| `bgapi-command` | stable | BgapiCommand, BgapiAcceptedReply, Job-UUID extraction |
| `inbound-decoding-facade` | stable | `InboundPipeline`, `DecodedInboundMessage`, and stable raw-byte ingress without depending on provisional parser/classifier classes directly |
| `reply-parsing` | stable | All typed reply classes, ReplyFactory, classifier → reply path |
| `event-subscription` | stable | EventSubscriptionCommand, FilterCommand, NoEventsCommand |
| `event-plain-decoding` | stable | EventParser decodes text/event-plain, URL-decodes values; bridge/playback paths are now backed by curated live plain captures |
| `event-json-decoding` | stable | EventParser decodes text/event-json into the same NormalizedEvent path; bridge/playback paths are now backed by curated live JSON captures |
| `event-xml-decoding` | provisional | EventParser decodes bounded `text/event-xml` documents into `NormalizedEvent`; currently backed by constructed fixtures rather than live captures |
| `normalized-events` | stable | NormalizedEvent, EventClassifier, typed event families; current live-backed bridge/playback evidence covers both plain and json formats |
| `correlation-metadata` | stable | ConnectionSessionId, ObservationSequence, CorrelationContext, metadata envelopes |
| `replay-envelope-export` | provisional | ReplayEnvelope, ReplayEnvelopeFactory, ReplayCapturePolicy |
| `reconstruction-hook-support` | provisional | ReconstructionHookInterface defined; no registry yet |
| `in-memory-transport` | stable | InMemoryTransport for testing |
| `fixture-replay-compatibility` | provisional | EslFixtureBuilder produces deterministic frames |

The live-backed bridge/playback capture evidence comes from non-public operator
tooling under `tools/smoke/`. That tooling is validation support only and is
not part of the package API or capability surface.
The internal stream/socket smoke transport now also validates fragmented,
coalesced, delayed-body, delayed-completion, and mid-frame-loss byte-stream
conditions, but that still does not make it a public transport API.

## Inspecting capabilities at runtime

```php
use Apntalk\EslCore\Capabilities\CapabilityMap;
use Apntalk\EslCore\Capabilities\Capability;
use Apntalk\EslCore\Capabilities\FeatureSupportLevel;

$map = new CapabilityMap();

$map->supports(Capability::Auth);              // true
$map->supports(Capability::CorrelationMetadata); // true

$map->supportLevel(Capability::ReplayEnvelopeExport); // FeatureSupportLevel::Provisional
```
