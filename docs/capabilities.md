# Capabilities

This document lists the declared capabilities of `apntalk/esl-core` and their current support levels.

Capabilities are backed by real tests and documentation. A capability is only declared once it is implemented and covered.

## Support levels

| Level | Meaning |
|---|---|
| `stable` | Implemented, fixture-backed, and stable at the documented supported seams |
| `provisional` | Implemented but signature or behavior may change before 1.0 |
| `unsupported` | Not yet implemented |

## Declared capabilities

| Capability | Level | Notes |
|---|---|---|
| `auth` | stable | Auth command, auth/request parsing, AuthAcceptedReply, ErrorReply |
| `api-command` | stable | ApiCommand serialization, ApiReply parsing |
| `bgapi-command` | stable | BgapiCommand, BgapiAcceptedReply, Job-UUID extraction |
| `inbound-decoding-facade` | stable | `InboundPipeline`, `DecodedInboundMessage`, and the preferred stable raw-byte ingress path without depending on provisional parser/classifier classes directly |
| `reply-parsing` | stable | Typed reply classes are stable; `ReplyFactory` remains a public advanced bridge for frame/classifier-owned composition rather than the preferred upper-layer ingress path. `fromFrame()` and `fromClassification()` are the explicit advanced public bridges, while `fromClassified()` remains available for the older lower-level path |
| `event-subscription` | stable | EventSubscriptionCommand, FilterCommand, NoEventsCommand |
| `event-plain-decoding` | stable | EventParser decodes text/event-plain, URL-decodes values; bridge/playback paths are now backed by curated live plain captures |
| `event-json-decoding` | stable | EventParser decodes text/event-json into the same NormalizedEvent path; bridge/playback paths are now backed by curated live JSON captures |
| `event-xml-decoding` | provisional | EventParser decodes bounded `text/event-xml` documents into `NormalizedEvent`; currently backed by constructed fixtures rather than live captures and requires PHP's `ext-dom` at install/runtime |
| `normalized-events` | stable | NormalizedEvent, `ProvidesNormalizedSubstrateInterface`, EventClassifier, EventFactory, and typed event families are stable for current selective decoding coverage; `EventFactory` / `EventClassifier` remain advanced public composition bridges rather than the preferred byte-ingress seam |
| `correlation-metadata` | stable | ConnectionSessionId, ObservationSequence, CorrelationContext, metadata envelopes |
| `replay-envelope-export` | provisional | ReplayEnvelope, ReplayEnvelopeFactory, ReplayCapturePolicy |
| `reconstruction-hook-support` | provisional | ReconstructionHookInterface defined; no registry yet |
| `in-memory-transport` | stable | InMemoryTransport for testing |
| `socket-transport-construction` | stable | `TransportFactoryInterface`, `SocketEndpoint`, and `SocketTransportFactory` provide the supported public seam for endpoint-based connect or wrapping accepted PHP stream resources |
| `inbound-connection-bootstrap` | stable | `InboundConnectionFactoryInterface`, `PreparedInboundConnection`, and `InboundConnectionFactory` provide the supported public seam for preparing one accepted inbound stream into transport + pipeline + correlation context |
| `fixture-replay-compatibility` | provisional | EslFixtureBuilder produces deterministic frames |

The live-backed bridge/playback capture evidence comes from non-public operator
tooling under `tools/smoke/`. That tooling is validation support only and is
not part of the package API or capability surface.
The internal stream/socket smoke transport now also validates fragmented,
coalesced, delayed-body, delayed-completion, and mid-frame-loss byte-stream
conditions, but that still does not make it a public transport API.
`Protocol\Frame` and `Protocol\HeaderBag` are also part of the supported
substrate because public reply/event contracts expose them, but that does not
promote the rest of the `Protocol\*` parsing pipeline to a preferred public seam.
Capability level and seam posture are separate axes: a capability may be
`stable` while some lower-level composition routes into that capability remain
advanced public seams rather than the default downstream integration path.
For byte ingress and accepted-stream bootstrap, prefer `InboundPipeline`,
`SocketTransportFactory`, and `InboundConnectionFactory` even when lower-level
factories or contracts remain public.
The same distinction applies to classified-message access: the public
read-only `ClassifiedMessageInterface` is available for advanced composition,
and `InboundMessageClassifierInterface` now returns that public result contract
so advanced classifier implementations no longer need the internal carrier.

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
