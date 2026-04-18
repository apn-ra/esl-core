# Protocol Model

This document describes how `apntalk/esl-core` models the FreeSWITCH Event Socket Layer (ESL) protocol.

## Wire format

ESL messages consist of:

1. Zero or more header lines: `Key: Value\n`
2. A blank line (`\n`) terminating the headers (makes `\n\n` after the last header)
3. Optionally, a body of exactly `Content-Length` bytes

Header values in the outer ESL frame are NOT URL-encoded.
Header values in `text/event-plain` event bodies ARE URL-encoded.
Header values in `text/event-json` are normalized from JSON scalar values and are not URL-decoded.
Header values in `text/event-xml` are normalized from XML element text and are not URL-decoded.

The current repo evidence for selective typed bridge/playback events is now
live-backed in both `text/event-plain` and `text/event-json` via curated
fixtures promoted from controlled loopback captures. That live evidence does
not make the operator tooling part of the package API.

## Frame parsing

The `FrameParser` accumulates bytes in an internal buffer and processes them as a state machine:

```
State: AwaitingHeaders
  - Look for \n\n in buffer
  - If found: parse header block → HeaderBag
  - If Content-Length present: transition to ReadingBody
  - If Content-Length absent: emit Frame with empty body, stay in AwaitingHeaders

State: ReadingBody
  - Wait for Content-Length bytes in buffer
  - When available: emit Frame with body, transition to AwaitingHeaders
```

The parser is partial-read safe: bytes may arrive in any chunk sizes.
It does not impose a maximum `Content-Length` / body-size cap. If a digit-only
`Content-Length` declares a large body, the parser will continue buffering until
that many bytes arrive or `finish()` reports truncation, as long as the declared
length is representable as a PHP integer. Values beyond that range are rejected
as malformed instead of being silently clamped by integer conversion. Memory
bounds and hostile-peer protection belong to the embedding transport/runtime
layer, not to `FrameParser` itself.

## Content-Type categories

| Content-Type | Meaning |
|---|---|
| `auth/request` | Server requests client authentication |
| `command/reply` | Response to client commands |
| `api/response` | Response to `api` commands |
| `text/event-plain` | Event in URL-encoded plain text format |
| `text/event-json` | Event in JSON format |
| `text/event-xml` | Event in XML format |
| `text/disconnect-notice` | Server closing connection |

## Command wire format

Commands sent by the client use this format:

```
<command-verb> [arguments]\n\n
```

Note: the double newline (`\n\n`) terminates every command. There is no `Content-Length` on outbound commands.

## Reply types

### auth/request
- No body.
- Client must respond with `auth <password>\n\n`.

### command/reply
Carries a `Reply-Text` header:
- `+OK accepted` — auth accepted
- `+OK Job-UUID: <uuid>` — bgapi accepted, job started
- `+OK [message]` — command accepted
- `-ERR [reason]` — command rejected

### api/response
- Has a body of exactly `Content-Length` bytes.
- Body is the raw API command output.
- `ApiReply::isSuccess()` is a narrow body-prefix check:
- `+OK ...` => `true`
- `-ERR ...` => `false`
- arbitrary non-prefixed output => `false`
- That means a command like `api status` may return healthy operational text while `ApiReply::isSuccess()` remains `false`. Callers that care about command-specific semantics must inspect the raw body.

### Unknown replies
- Unsupported or non-reply classified inputs degrade to `UnknownReply`.
- `UnknownReply::isSuccess()` is conservatively `false`.
- That `false` means "not known-success" on the typed reply contract, not "this frame was positively classified as a protocol error reply."

### text/event-plain
- Has a body of exactly `Content-Length` bytes.
- Body is itself a set of URL-encoded header lines (`Key: Value\n`), terminated by `\n\n`.
- The event may have its own body (for `BACKGROUND_JOB` etc.); if so, the event headers include a `Content-Length` header and the body follows after `\n\n`.

### text/event-json
- Has a body of exactly `Content-Length` bytes.
- Body must decode to a JSON object.
- Top-level scalar entries are normalized into event headers.
- Optional `_body` is treated as the event body.
- Nested objects, arrays, null header values, or invalid JSON fail deterministically.

## Event body structure (text/event-plain)

```
Outer frame:
  Content-Type: text/event-plain\n
  Content-Length: N\n
  \n
  [N bytes: event data]

Event data structure:
  Event-Name: CHANNEL_CREATE\n      ← URL-encoded header
  Unique-ID: a3ebbd02-...\n         ← URL-encoded header
  ...more event headers...\n
  \n
  [optional event body if Content-Length present in event headers]
```

## bgapi protocol distinction

This is critical: `bgapi` has two distinct phases.

**Phase 1: Acceptance** (immediate)
```
command/reply
Reply-Text: +OK Job-UUID: 7f4db0f2-...
```
This means: "I accepted your bgapi command and started a job with this UUID."
It is NOT the command result.

**Phase 2: Result** (deferred)
```
text/event-plain
Content-Length: N

Event-Name: BACKGROUND_JOB
Job-UUID: 7f4db0f2-...
...
Content-Length: M

<M bytes: actual command output>
```

The result event is correlated to the bgapi command via `Job-UUID`.

`BgapiAcceptedReply.jobUuid()` provides the correlation key.
`BackgroundJobEvent.jobUuid()` provides the matching value.

## URL encoding and JSON normalization

Header values in `text/event-plain` bodies are percent-encoded. Common encodings:
- `%20` = space
- `%40` = @
- `%3A` = :
- `%2C` = ,

`NormalizedEvent.header()` and all named accessors (`.channelName()`, `.callerIdName()`, etc.) automatically decode these values.
`NormalizedEvent.rawHeader()` returns the raw encoded value for diagnostics.

For `text/event-json`, top-level scalar JSON values are normalized directly and are not URL-decoded.
Current JSON normalization intentionally accepts only:
- scalar top-level header values
- optional `_body` string for the event body

This keeps the JSON path deterministic and aligned with the existing normalized event model.

### text/event-xml
- Has a body of exactly `Content-Length` bytes.
- Body must decode to an XML document rooted at `<event>`.
- `<event>` must contain one `<headers>` element whose child elements become normalized event headers.
- Optional `<body>` becomes the event body.
- Nested XML elements inside header values are rejected to preserve deterministic, scalar normalization.
- Current XML support is intentionally narrower and remains provisional until the fixture corpus extends beyond constructed cases.

## Malformed input behavior

| Condition | Behavior |
|---|---|
| Header line with no colon separator | `MalformedFrameException` |
| Empty or whitespace-only header name | `MalformedFrameException` |
| Header name with leading or trailing whitespace | `MalformedFrameException` |
| Non-numeric `Content-Length` | `MalformedFrameException` |
| `Content-Length` beyond PHP's integer range | `MalformedFrameException` |
| Incomplete body (truncated) | No frame emitted; buffered |
| Missing header terminator at end of input | `TruncatedFrameException` from `FrameParser::finish()` |
| Unknown `Content-Type` | `ClassifiedInboundMessage.category == Unknown` |
| Unsupported event parser content type | `UnsupportedContentTypeException` |
| Invalid `text/event-json` payload | `MalformedFrameException` |
| Event inner `Content-Length` that is non-numeric, beyond PHP's integer range, or does not match the event body length | `MalformedFrameException` |
| Event with unknown name | `RawEvent` (no exception) |
| Empty event body | `NormalizedEvent.hasBody() == false` |
