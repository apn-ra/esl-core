# Protocol Fixtures

Fixtures are deterministic protocol evidence used by tests to validate parser and classifier behavior.

## Location

```
tests/Fixtures/
├── EslFixtureBuilder.php  — programmatic frame builder
├── FixtureLoader.php      — file-based fixture loader
├── auth/                  — authentication flow frames
├── commands/              — outbound command frames (serialized)
├── replies/               — inbound reply frames
├── events/                — inbound event frames
├── malformed/             — intentionally bad frames for error path tests
├── partial/               — truncated frames for partial-read tests
├── sequences/             — multi-frame inbound byte sequences
└── replay/                — replay envelope fixtures
```

## Fixture strategy

Fixtures come in two forms:

### 1. Programmatic fixtures (`EslFixtureBuilder`)

`EslFixtureBuilder` builds complete, byte-accurate ESL frames from their constituent parts. `Content-Length` values are computed at runtime using `strlen()`, eliminating byte-counting errors.

This is the preferred way to write test cases. Use `EslFixtureBuilder` instead of writing raw byte strings inline in tests.

```php
// Build an auth/request frame
$frame = EslFixtureBuilder::authRequest();

// Build a CHANNEL_CREATE event frame with custom UUID
$frame = EslFixtureBuilder::channelCreateEvent(uniqueId: 'abc-123');

// Build an XML event fixture
$frame = EslFixtureBuilder::eventXml(
    EslFixtureBuilder::eventXmlData([
        'Event-Name' => 'CHANNEL_CREATE',
        'Unique-ID' => 'abc-123',
    ])
);

// Build any frame from headers + body
$frame = EslFixtureBuilder::frame(
    headers: ['Content-Type' => 'command/reply', 'Reply-Text' => '+OK'],
    body: '',
);
```

### 2. File-based fixtures (`FixtureLoader`)

Raw `.esl` files in subdirectories contain static byte strings for documentation and regression purposes. These are especially useful for edge cases captured from real FreeSWITCH connections.

```php
$bytes = FixtureLoader::load('auth/auth-request.esl');
```

File-based fixtures MUST:
- Use LF (`\n`) line endings only. No `\r\n`.
- Include a correct `Content-Length` where required.
- Preserve the exact parser input bytes. Do not add inline comments to raw
  `.esl` / `.bin` fixtures when those comments would become part of the frame.
- Document provenance in a nearby `README.md`, a central provenance document, or
  the test that consumes the fixture.

Promoted live fixtures should also be listed in a central provenance surface.
See `docs/live-fixture-provenance.md` for the current live-backed capture map.

## Naming scheme

| Directory | Pattern | Contents |
|---|---|---|
| `auth/` | `auth-{description}.esl` | Auth flow frames |
| `replies/` | `{type}-{description}.esl` | Inbound reply frames |
| `events/` | `{event-name}-{description}.esl` | Inbound event frames |
| `malformed/` | `{description}.esl` | Intentionally broken parser or event-parser inputs |
| `partial/` | `{description}-partial.bin` | Truncated or fragmented frames |
| `sequences/` | `{description}.esl` | Multi-frame inbound captures or constructed protocol flows |
| `replay/` | `{description}.json` | Replay envelope shapes |

## Fixture provenance

Fixtures should document their origin:

- `# provenance: constructed` — built from the FreeSWITCH ESL documentation
- `# provenance: captured` — captured from a live FreeSWITCH connection
- `# provenance: regression` — created to reproduce a specific bug

These labels may appear in sidecar fixture documentation instead of inside the
raw fixture file. For malformed and partial parser fixtures, sidecar
documentation is preferred because changing the raw bytes changes the condition
being tested.

For curated live fixtures promoted from `tools/smoke/captures/`, maintainers
should record:

- the exact quarantined capture filename
- the capture mode (`plain` or `json`)
- the controlled scenario that produced it
- the reason it was promoted
- the contract test(s) that now pin it

Constructed XML fixtures should explicitly remain labeled as constructed
protocol corpus rather than implied live captures.

## Adding new fixtures

1. Create the fixture using `EslFixtureBuilder` in a test first.
2. If a file-based fixture is needed (e.g., for a known-bad frame), add it to the appropriate subdirectory.
3. Document the fixture behavior in a sidecar README, central provenance document, or focused test when inline comments would alter raw wire truth.
4. Add a test that consumes the fixture through the relevant parser or classifier.

## Fixture validation rules

- A fixture for a valid frame MUST parse without exceptions.
- A fixture in `malformed/` MUST cause a `ParseException` when fed through the
  relevant parser layer. Pure frame-shape fixtures should fail in
  `FrameParser`; event-payload fixtures may parse as an outer frame first and
  then fail in `EventParser` / `InboundPipeline`.
- A fixture in `partial/` MUST produce zero frames when fed as a single read and
  MUST produce `TruncatedFrameException` when `FrameParser::finish()` is called
  without supplying the missing bytes. If a completion counterpart is added, it
  should prove that the same prefix can produce one complete frame once the
  missing bytes arrive.
- Event fixtures MUST contain normalized event names that match classifier expectations.
