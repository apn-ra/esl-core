# Revised Implementation Plan for `apntalk/esl-core`

## Purpose

`apntalk/esl-core` should be:

**a framework-agnostic, transport-neutral, typed FreeSWITCH ESL protocol library for PHP with replay-safe protocol primitives and reconstruction-oriented extension contracts**

It should sit below:

- `apntalk/esl-react`
- `apntalk/laravel-freeswitch-esl`

and should not depend on Laravel, ReactPHP, Amp, or any specific event loop/runtime model.

The core design constraint is this:

APNTalk’s real FreeSWITCH-side blocker is not merely the absence of a PHP ESL client. The larger gap is the lack of a truthful replay-adjacent substrate: protocol-safe capture, deterministic reconstruction inputs, normalized correlation identity, and extension points for replay-aware upper layers.

Because of that, `apntalk/esl-core` should be designed as a **protocol substrate**, not just a command sender.

---

## Package role

`apntalk/esl-core` is the package that should define:

- the ESL wire model
- framing and parsing behavior
- command serialization
- reply parsing
- message classification
- typed event normalization
- correlation metadata attachment
- replay-safe protocol envelopes
- reconstruction-oriented hook contracts
- capability declaration of supported surfaces

It is **not** the package that should define:

- Laravel service integration
- ReactPHP integration
- event loop ownership
- reconnect supervisors
- worker assignment or workload routing
- cluster or PBX orchestration
- database-backed registry behavior
- runtime health endpoints
- durable replay execution engines
- long-lived supervision policies

---

## Non-goals

To keep package identity clear, these are explicit non-goals for `esl-core`:

- being a full FreeSWITCH operational runtime
- hiding all ESL protocol truth behind high-level abstractions
- acting as a Laravel package in disguise
- bundling transport supervision and orchestration into protocol code
- implementing a complete replay system instead of replay-safe protocol primitives
- promising exhaustive typed coverage of every ESL event name from day one

---

## Package structure

```text
apntalk/esl-core/
  composer.json
  README.md
  CHANGELOG.md
  docs/
    architecture.md
    protocol-model.md
    protocol-state.md
    fixtures.md
    replay-primitives.md
    public-api.md
    stability-policy.md
    capabilities.md
  src/
    Contracts/
    Protocol/
    Parsing/
    Serialization/
    Commands/
    Replies/
    Events/
    Correlation/
    Replay/
    Capabilities/
    Transport/
    Support/
    Exceptions/
    Internal/
  tests/
    Unit/
    Contract/
    Integration/
    Fixtures/
```

### Structure rules

- `src/Internal/` is explicitly unstable and not part of the supported public API.
- `src/Support/` should be used sparingly and must not become a dumping ground for unstable internals.
- `tests/Fixtures/` is part of the truth surface for parser and model behavior.
- `docs/public-api.md` and `docs/stability-policy.md` should define what consumers are allowed to depend on.

---

## Design principles

### 1. Transport-neutral first
Core must not commit to sync sockets, ReactPHP, Amp, Laravel, or any runtime loop. It may define transport contracts, but it must not own scheduler or loop behavior.

### 2. Protocol-truthful first
The library should model the ESL protocol honestly. It should not flatten away state, category, or sequencing details that matter to correct consumers.

### 3. Typed API first, with raw escape hatches
Typed commands, replies, normalized events, and metadata-bearing envelopes should be first-class. Raw payload access may exist, but must not dominate the design.

### 4. Replay-safe from day one
Replay-related protocol primitives should be present from the start, but the package must stop at substrate-level concerns, not full replay runtime ownership.

### 5. Public API discipline
The public surface should be intentionally small. Package boundary should stabilize early; detailed type commitments should stabilize only when validated by fixtures and usage.

### 6. Fixture-driven correctness
Protocol behavior must be proven against deterministic fixtures. Parsers and classifiers should be evidence-backed, not guess-driven.

### 7. Additive evolution before 1.0
Before `1.0.0`, the package may evolve its detailed model, but it should avoid avoidable churn by clearly marking provisional surfaces.

---

## Architecture model

Think of `esl-core` in layers:

1. **Wire layer**
    - bytes
    - headers
    - body
    - framing
    - parsing
    - serialization

2. **Message classification layer**
    - message categories
    - session/auth state
    - command reply vs unsolicited event
    - `bgapi` acceptance vs later job completion

3. **Typed domain layer**
    - commands
    - replies
    - normalized events
    - correlation metadata

4. **Replay-safe substrate layer**
    - replay envelopes
    - capture policies
    - reconstruction hook contracts

5. **Optional transport boundary**
    - minimal read/write transport abstraction
    - in-memory and minimal smoke-path implementations

This layering helps keep protocol truth separate from runtime orchestration.

---

## Public API and stability policy

### Early stability promise
Stabilize early:

- package purpose
- namespace boundaries
- public vs internal classification
- SemVer policy
- raw/typed boundary rules
- fixture-driven compatibility expectations

Do **not** fully stabilize too early:

- every interface in `Contracts`
- exhaustive typed event subclasses
- replay helper registries and convenience types
- transport details beyond minimal contracts

### Public namespaces
These should be treated as the intended package boundary:

- `Apntalk\EslCore\Contracts`
- `Apntalk\EslCore\Commands`
- `Apntalk\EslCore\Replies`
- `Apntalk\EslCore\Events`
- `Apntalk\EslCore\Correlation`
- `Apntalk\EslCore\Replay`
- `Apntalk\EslCore\Capabilities`
- `Apntalk\EslCore\Exceptions`

### Unstable/internal namespaces
These should be explicitly unstable:

- `Apntalk\EslCore\Internal`
- implementation detail classes not documented in `docs/public-api.md`

---

## Revised implementation plan

## Phase 1 — Repository foundation and boundary policy

### Goal
Create a clean package skeleton, define package identity, and establish a clear stability policy without prematurely freezing detailed protocol interfaces.

### Build
- `composer.json`
- PHPUnit or Pest setup
- PHPStan and Psalm
- coding standard configuration
- CI workflow
- `docs/public-api.md`
- `docs/stability-policy.md`
- namespace policy for public vs internal classes
- contribution guidance for adding new protocol fixtures and typed surfaces

### Decisions to document
- supported PHP versions
- public vs internal namespace policy
- what counts as public API
- whether raw escape hatches are part of the supported contract
- how pre-1.0 additions and refactors are handled
- how fixture-backed behavior changes must be reviewed

### Exit criteria
- package installs cleanly
- CI runs
- public API policy exists
- stability policy exists
- internal namespaces are marked unstable
- no detailed interface set is prematurely declared fully stable

---

## Phase 2 — Protocol corpus and fixture inventory

### Goal
Build the evidence base for the package before expanding implementation complexity.

### Build fixture corpus for:
- auth requests and replies
- `api` replies
- `bgapi` acceptance replies
- background job completion events
- event frames
- malformed frames
- partial frames
- fragmented/multi-read frames
- multi-line body cases
- repeated header cases
- header normalization edge cases
- unknown content types
- empty-body and zero-length cases
- mixed reply/event ordering scenarios if observed in captures

### Output
- `tests/Fixtures/auth/`
- `tests/Fixtures/commands/`
- `tests/Fixtures/replies/`
- `tests/Fixtures/events/`
- `tests/Fixtures/malformed/`
- `tests/Fixtures/partial/`
- `tests/Fixtures/replay/`

### Documentation
- `docs/fixtures.md`
- naming scheme for fixtures
- fixture provenance notes
- rules for deterministic fixture loading

### Exit criteria
- fixture corpus exists
- naming scheme is documented
- fixtures load deterministically
- malformed and partial cases are part of the standard corpus, not an afterthought

---

## Phase 3 — Wire model and codec

### Goal
Implement the raw protocol foundation for parsing and serialization.

### Build
- `HeaderBag`
- `Frame`
- `Message`
- `ProtocolCodec`
- `FrameParser`
- `FrameSerializer`

### Responsibilities
- parse raw bytes into frame/message objects
- preserve headers and body faithfully
- serialize messages back to valid ESL protocol format
- support partial frame assembly
- enforce consistent header access semantics
- make malformed input failure predictable

### Contracts
- `FrameParserInterface`
- `FrameSerializerInterface`
- `ProtocolCodecInterface`

### Design rules
- keep this layer free of domain assumptions beyond protocol structure
- do not hide malformed input behind silent coercion
- preserve enough raw information for downstream reconstruction and diagnostics

### Exit criteria
- fixture frames parse deterministically
- round-trip behavior works where round-trip is valid
- malformed input fails predictably
- no framework/runtime dependency exists

---

## Phase 4 — Protocol state and message classification

### Goal
Make protocol behavior truthful by modeling message categories and session state before the higher-level typed API expands.

### Build
- `SessionState`
- `MessageType`
- `InboundMessageClassifier`
- `ReplyDisposition`
- `EventDisposition`
- `AuthState`
- `CommandAcceptanceState`

### Responsibilities
- distinguish pre-auth from post-auth protocol behavior
- distinguish command replies from unsolicited events
- distinguish `bgapi` acceptance from later background job completion
- document how message categories are derived
- prevent higher layers from inferring protocol mode ad hoc

### Why this phase matters
A parser alone is not enough. A truthful ESL substrate must model when a message is a reply, when it is an event, and how session state affects interpretation.

### Exit criteria
- message classification is deterministic in tests
- protocol state transitions are documented
- typed command/reply/event layers do not need to infer state from scattered heuristics

---

## Phase 5 — Typed command and reply model

### Goal
Move from raw strings and loosely typed messages to a typed command/reply surface.

### Build commands
- `AuthCommand`
- `ApiCommand`
- `BgapiCommand`
- `EventSubscriptionCommand`
- `FilterCommand`
- `NoEventsCommand`
- `ExitCommand`
- `RawCommand`

### Build replies
- `AuthAcceptedReply`
- `CommandReply`
- `ErrorReply`
- `BgapiAcceptedReply`
- `UnknownReply`

### Add factories
- `CommandFactory`
- `ReplyFactory`

### Contracts
- `CommandInterface`
- `ReplyInterface`
- `CommandResultInterface`

### Design rules
- typed commands should be first-class
- raw fallback must exist, but should not define the main package experience
- replies should expose structured accessors where semantics are stable
- reply typing should be grounded in classifier behavior from Phase 4

### Exit criteria
- common command types serialize correctly
- common reply types parse correctly
- raw command fallback exists
- typed replies align with message classification rules

---

## Phase 6 — Normalized event model and selective typed events

### Goal
Provide a stable event surface through normalization first, with typed subclasses only where they offer durable value.

### Build core event surface
- `EventInterface`
- `BaseEvent`
- `RawEvent`
- `NormalizedEvent`
- `EventParser`
- `EventClassifier`
- `EventFactory`

### Build selected typed event families only
Recommended initial typed event families:
- `BackgroundJobEvent`
- `ChannelLifecycleEvent`
- `BridgeEvent`
- `HangupEvent`
- `PlaybackEvent`
- `CustomEvent`

Do **not** assume that every known ESL event name needs its own public class in the initial release line.

### Every event should expose normalized accessors for:
- event name
- event family/category
- unique event ID if available
- channel UUID if present
- job UUID if present
- call UUID or related correlation identifiers if present
- timestamp if present
- normalized identifier map
- headers
- body
- raw source reference where useful

### Design rules
- stabilize normalization behavior before expanding subclass count
- unknown events must degrade safely to `RawEvent`
- classifiers should be deterministic and fixture-backed
- high-value semantic families may get dedicated types; low-value specialization may remain classifier metadata

### Exit criteria
- event fixtures decode deterministically
- important identifiers are normalized consistently
- unknown or unsupported events degrade safely
- typed event families are limited to those justified by fixtures and downstream needs

---

## Phase 7 — Correlation and session primitives

### Goal
Make protocol objects useful to upper layers by attaching correlation and observation metadata without binding the core to framework or worker logic.

### Build
- `ConnectionSessionId`
- `CorrelationContext`
- `ChannelCorrelation`
- `JobCorrelation`
- `EventEnvelope`
- `MessageMetadata`
- `ObservationSequence` or equivalent ordering primitive

### Responsibilities
- preserve protocol identifiers
- attach session and observation metadata
- make `bgapi` and async job handling first-class
- distinguish source identifiers from derived correlation identifiers
- preserve enough ordering data for later replay-safe use

### Design rules
Correlation is not just “extra metadata.” It should distinguish:
- protocol-native identifiers
- transport/session identifiers
- derived correlation identifiers
- observation order/capture metadata

### Exit criteria
- command/reply/event flows can be correlated in tests
- session metadata is attachable without global state
- ordering and lineage information are preserved consistently

---

## Phase 8 — Replay-safe primitives and reconstruction contracts

### Goal
Provide the minimal replay-safe substrate required by upper layers, without turning `esl-core` into a replay runtime.

### Build
- `ReplayEnvelope`
- `ReplayEnvelopeInterface`
- `ReplayEnvelopeFactory`
- `ReplayCaptureSinkInterface`
- `ReplayCapturePolicy`
- `ReconstructionHookInterface`

### Optional and explicitly unstable until proven necessary
- `ReconstructionHookRegistry`
- `ReplayCursor`
- `FixtureExporter`
- `FixtureImporter`

### Replay envelope should preserve, at minimum:
- captured object type
- normalized identifiers
- capture/order metadata
- session metadata
- protocol timestamp if present
- capture timestamp if assigned
- raw payload or deterministic reconstruction reference
- classifier context needed for later reconstruction/audit

### Design rules
`esl-core` should provide:
- deterministic replay envelope shape
- capture sink contract
- capture policy contract
- reconstruction hook contract

`esl-core` should not provide:
- full replay runtime
- durable storage engine
- scheduling/execution orchestration
- worker replay lifecycle management
- cluster replay coordination

### Exit criteria
- replies and events can be wrapped in deterministic replay envelopes
- envelope serialization shape is stable and testable
- reconstruction hooks attach through clear contracts
- replay primitives remain transport-neutral and framework-neutral

---

## Phase 9 — Capability map and support declaration

### Goal
Make supported surfaces explicit instead of inferred.

### Build
- `CapabilityMap`
- `Capability`
- `FeatureSupportLevel`

### Example capabilities
- auth
- command execution
- reply parsing
- `bgapi` acceptance modeling
- background job event decoding
- normalized event decoding
- correlation metadata attachment
- replay envelope export
- reconstruction hook support
- deterministic fixture replay compatibility

### Design rules
- capabilities should be backed by real tests and documentation
- avoid decorative declarations that drift from reality
- support levels should reflect actual guarantees, not aspiration

### Exit criteria
- capability map is inspectable
- supported capabilities are documented
- tests verify that declared capabilities match implemented surfaces

---

## Phase 10 — Minimal transport abstraction and test transport

### Goal
Provide just enough transport abstraction for sync use and testing without turning core into an async runtime library.

### Build
- `TransportInterface`
- `ReadableTransportInterface`
- `WritableTransportInterface`
- `InMemoryTransport`

### Optional
- `StreamSocketTransport` as a minimal smoke-path only

### Rules
- keep transport abstraction small
- do not add reconnect loops
- do not add supervision/orchestration behavior
- do not let transport become the center of package identity

### Exit criteria
- tests can run entirely in memory
- protocol logic is testable independent of runtime loop choice
- optional socket smoke-path can exist without redefining the package mission

---

## Phase 11 — Error taxonomy and hardening

### Goal
Make failure behavior predictable, inspectable, and operable.

### Build
- `ProtocolException`
- `ParseException`
- `SerializationException`
- `UnexpectedReplyException`
- `UnknownEventException`
- `TransportException`

### Add hardening
- malformed frame defense
- truncation handling
- partial frame handling rules
- header normalization rules
- classifier failure behavior
- unsupported content-type handling
- deterministic error messaging where practical

### Design rules
- consumers should be able to distinguish protocol failures from transport failures
- unknown input should fail safely, not ambiguously
- error taxonomy should reflect package layering

### Exit criteria
- bad inputs fail consistently
- exception taxonomy is documented
- consumers can distinguish protocol, classification, and transport failures

---

## Phase 12 — Documentation and examples

### Goal
Make the library understandable, adoptable, and maintainable.

### Docs to write
- `architecture.md`
- `protocol-model.md`
- `protocol-state.md`
- `replay-primitives.md`
- `fixtures.md`
- `public-api.md`
- `stability-policy.md`
- `capabilities.md`

### Examples
- sync auth + command send example
- reply parse example
- normalized event decode example
- `bgapi` correlation example
- replay envelope export example
- in-memory transport test example

### Documentation rules
- examples should reflect the real public API
- example code should compile or run under tests
- docs should distinguish stable API from provisional/internal details

### Exit criteria
- a new maintainer can understand the architecture
- examples stay in sync with code
- docs explain what belongs in upper layers vs core

---

## Phase 13 — Release staging

### Goal
Ship in controlled layers that reflect actual package maturity.

### Recommended release ladder
- `0.1.x` repository foundation + fixtures + codec
- `0.2.x` protocol state + typed commands/replies
- `0.3.x` normalized events + correlation
- `0.4.x` replay-safe primitives + reconstruction contracts
- `0.5.x` capability declaration + minimal transport + hardening
- `1.0.0` only when fixture coverage, normalization behavior, replay envelope shape, and public API guarantees are all stable

### Release rule
Do not use `1.0.0` to mean “feature complete.”  
Use `1.0.0` to mean “public behavior and compatibility expectations are stable.”

---

## Core interfaces to define early

These are the interfaces worth defining early, but not all of them need to be treated as permanently frozen from the first release:

```php
interface FrameParserInterface {}
interface FrameSerializerInterface {}
interface ProtocolCodecInterface {}

interface CommandInterface {}
interface ReplyInterface {}
interface EventInterface {}

interface EventParserInterface {}
interface EventFactoryInterface {}

interface ReplayEnvelopeInterface {}
interface ReplayCaptureSinkInterface {}
interface ReconstructionHookInterface {}

interface CapabilityMapInterface {}
interface TransportInterface {}
```

### Additional recommended protocol-state interfaces

```php
interface InboundMessageClassifierInterface {}
interface SessionStateInterface {}
```

### Guidance
Define these early for architectural clarity, but treat detailed signatures as provisional until validated by fixtures and actual usage.

---

## Testing strategy

### Test layers

#### Unit tests
For:
- header handling
- parser pieces
- serializers
- classifiers
- identifier normalization
- exception behavior

#### Contract tests
For:
- fixture-backed parser behavior
- message classification
- typed reply decoding
- normalized event decoding
- replay envelope serialization

#### Integration tests
For:
- end-to-end parse/classify/decode flows
- in-memory transport usage
- optional smoke-path socket behavior

### Testing rules
- every protocol bug should first become a fixture
- replay-related shapes must be deterministic in tests
- typed event additions should require fixture proof
- classifier behavior should be tested independently from transport concerns

---

## What success looks like

A successful `apntalk/esl-core` is not merely a package that can:

- connect
- auth
- send command
- parse event

A successful `apntalk/esl-core` gives you:

- a truthful ESL wire and message model
- a stable boundary for higher-level runtime packages
- deterministic fixture-backed behavior
- explicit protocol-state classification
- typed commands and replies
- normalized events with safe degradation
- correlation and observation metadata
- replay-safe protocol envelopes
- reconstruction-oriented extension contracts
- a clean substrate for future ReactPHP, Laravel, and replay-aware upper layers

That is the kind of core that can support APNTalk’s longer-term FreeSWITCH substrate direction instead of becoming another thin wrapper.
