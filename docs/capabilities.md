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
| `reply-parsing` | stable | All typed reply classes, ReplyFactory, classifier → reply path |
| `event-subscription` | stable | EventSubscriptionCommand, FilterCommand, NoEventsCommand |
| `event-plain-decoding` | stable | EventParser decodes text/event-plain, URL-decodes values |
| `event-json-decoding` | stable | EventParser decodes text/event-json into the same NormalizedEvent path |
| `normalized-events` | stable | NormalizedEvent, EventClassifier, typed event families |
| `correlation-metadata` | stable | ConnectionSessionId, ObservationSequence, CorrelationContext, metadata envelopes |
| `replay-envelope-export` | provisional | ReplayEnvelope, ReplayEnvelopeFactory, ReplayCapturePolicy |
| `reconstruction-hook-support` | provisional | ReconstructionHookInterface defined; no registry yet |
| `in-memory-transport` | stable | InMemoryTransport for testing |
| `fixture-replay-compatibility` | provisional | EslFixtureBuilder produces deterministic frames |

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
