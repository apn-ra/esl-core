# Canonical Truth Vocabulary

`apntalk/esl-core` is the canonical vocabulary source for protocol/core truth
shared by the APNTalk FreeSWITCH substrate family.

This document names the stable vocabulary surfaces that downstream runtime,
framework, and replay packages may import without also importing runtime
behavior.

## Scope

The vocabulary surface provides:

- capability declarations for blocker-family truth surfaces
- queue, retry, drain, recovery-generation, in-flight operation, replay
  continuity, and reconstruction posture vocabulary
- terminal-publication schema objects
- lifecycle semantic observations for downstream projection work
- replay-envelope identity, ordering, and causal truth accessors
- corpus/row identity and bounded-variance markers

It does not provide:

- queue execution
- retry scheduling
- drain orchestration
- reconnect supervision
- lifecycle projection state machines
- terminal publication dispatch
- durable replay execution, re-injection, cursor running, or scheduling

## Public Namespace

Canonical vocabulary types live in `Apntalk\EslCore\Vocabulary`.

The namespace is public because the terms cut across correlation, replay,
event, and downstream projection work. Keeping them in one namespace avoids
turning `Replay` or `Events` into runtime vocabulary buckets.

## Queue, Retry, Drain, Recovery, and Replay Vocabulary

The public enums/value objects are:

| Type | Purpose |
|---|---|
| `QueueState` | Describes queue membership and drain-facing states |
| `RetryPosture` | Describes retry eligibility/current posture |
| `RetryAttempt` | Carries operation ID, attempt number, max attempts, and posture |
| `DrainPosture` | Describes drain status without executing a drain |
| `InFlightOperationId` | Identifies a currently tracked downstream operation |
| `RecoveryGenerationId` | Identifies a reconnect/recovery generation assigned by an upper layer |
| `ReplayContinuity` | Describes continuity or gap status for replay-adjacent work |
| `ReconstructionPosture` | Describes whether native data is enough or hooks are required |
| `BoundedVarianceMarker` | Marks intentionally non-binary truth |

The fixtures under `tests/Fixtures/vocabulary/queue-retry-drain.json` pin the
serialized vocabulary values.

## Terminal Publication Schema

`TerminalPublication` is the public terminal-publication truth schema. It can
express:

- publication identity through `PublicationId`
- finality through `FinalityMarker`
- terminal cause through `TerminalCause`
- publication source through `PublicationSource`
- publication timestamp in microseconds
- ordering identity through `OrderingIdentity`
- optional corpus/row identity through `CorpusRowIdentity`
- ambiguity/provisionality through `BoundedVarianceMarker`

The schema is stable as vocabulary. Dispatching or persisting a terminal
publication remains an upper-layer concern.

## Lifecycle Semantic Contract

`LifecycleSemanticObservation` describes one semantic observation for downstream
projection. It supports the transition vocabulary:

- `transfer`
- `bridge`
- `hold`
- `resume`
- `terminal`

The semantic state is explicitly one of confirmed, provisional, ambiguous,
bounded-variance, or unknown. Core does not compare providers, own a lifecycle
state machine, or publish aggregate call lifecycle status.

## Replay-Envelope Truth Surface

`ReplayEnvelopeInterface` now exposes:

| Method | Meaning |
|---|---|
| `schemaVersion()` | Current envelope schema identifier, `replay-envelope.v1` |
| `identityFacts()` | Stable comparison facts such as captured type/name, session ID, content type, event name, core UUID, unique ID, and job UUID |
| `orderingFacts()` | Stable reconstruction-ordering facts such as capture sequence, protocol sequence, event timestamp, observation sequence, and observation timestamp |
| `causalMetadata()` | Explicit causal facts such as reply text, event name, job correlation, and channel correlation |

The replay envelope is tighter, but still not a replay runtime. `ReplayEnvelope`
shape and these accessor groups are stable for current fixture-backed behavior;
broader replay execution policy remains provisional and belongs to
upper-layer packages.

## Capability Declarations

`CapabilityMap` declares stable support for the canonical vocabulary surfaces:

- `native-replay-adjacent-semantics`
- `queue-retry-drain-vocabulary`
- `terminal-publication-schema`
- `lifecycle-semantic-contract`
- `corpus-row-identity`
- `bounded-variance-markers`

`replay-envelope-export` and `reconstruction-hook-support` remain provisional
because core still does not own replay execution, hook registries, durable
storage, or re-injection.
