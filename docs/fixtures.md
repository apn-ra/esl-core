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
- Have a comment at the top describing provenance.

## Naming scheme

| Directory | Pattern | Contents |
|---|---|---|
| `auth/` | `auth-{description}.esl` | Auth flow frames |
| `replies/` | `{type}-{description}.esl` | Inbound reply frames |
| `events/` | `{event-name}-{description}.esl` | Inbound event frames |
| `malformed/` | `{description}.esl` | Intentionally broken frames |
| `partial/` | `{description}-partial.bin` | Truncated or fragmented frames |
| `replay/` | `{description}.json` | Replay envelope shapes |

## Fixture provenance

Fixtures should document their origin:

- `# provenance: constructed` — built from the FreeSWITCH ESL documentation
- `# provenance: captured` — captured from a live FreeSWITCH connection
- `# provenance: regression` — created to reproduce a specific bug

## Adding new fixtures

1. Create the fixture using `EslFixtureBuilder` in a test first.
2. If a file-based fixture is needed (e.g., for a known-bad frame), add it to the appropriate subdirectory.
3. Document the fixture behavior in a comment within the fixture file.
4. Add a test that consumes the fixture through the relevant parser or classifier.

## Fixture validation rules

- A fixture for a valid frame MUST parse without exceptions.
- A fixture in `malformed/` MUST cause a `ParseException` when fed to the parser.
- A fixture in `partial/` MUST produce zero frames when fed as a single read, and one complete frame when the remainder is fed.
- Event fixtures MUST contain normalized event names that match classifier expectations.
