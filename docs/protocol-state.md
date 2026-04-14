# Protocol State

This document describes the ESL connection state model and its implications for message classification.

## Session states

An ESL connection passes through the following states:

```
CONNECTING → AWAITING_AUTH → AUTHENTICATED → [normal operation] → DISCONNECTED
                          ↓
                    AUTH_FAILED
```

### CONNECTING
The TCP connection is being established. No ESL frames exchanged yet.

### AWAITING_AUTH
FreeSWITCH sends `auth/request`. The client must respond with `auth <password>\n\n`.
No other commands should be sent until authentication succeeds.

### AUTHENTICATED
An `auth/request` reply with `Reply-Text: +OK accepted` was received.
The client may now send commands and subscribe to events.

### AUTH_FAILED
A `command/reply` with `Reply-Text: -ERR ...` was received during the auth exchange.
The connection should be closed.

### DISCONNECTED
A `text/disconnect-notice` was received, or the transport was closed.

---

## The classification limitation

The `InboundMessageClassifier` classifies individual frames in isolation.
It cannot determine session state from a single frame.

Specifically: it CANNOT distinguish between:
- A `-ERR` reply to the auth command (should map to `AUTH_FAILED`)
- A `-ERR` reply to any other command (should map to `CommandError`)

Both produce `InboundMessageCategory::CommandError` at the classifier level.

**The caller** (upper layer or session-state manager) must track whether auth has succeeded and interpret `CommandError` accordingly:
- If auth has not yet completed: `CommandError` means `AUTH_FAILED`
- If auth has completed: `CommandError` means a command was rejected

The `ReplyFactory` maps both to `ErrorReply`, which is correct — the distinction
is semantic/contextual, not structural.

When a caller asks for a more specific typed reply directly, reply constructors now
fail explicitly on structurally incompatible shapes:
- `BgapiAcceptedReply` requires `Reply-Text: +OK Job-UUID: <uuid>`
- `AuthAcceptedReply` requires `Reply-Text: +OK accepted`
- `ApiReply` requires `Content-Type: api/response`

`ApiReply::isSuccess()` is intentionally narrow. It only returns `true` when the
response body begins with `+OK`. Some FreeSWITCH API commands instead return raw
operational text on success. Those still produce a valid `ApiReply`, but callers
must inspect `body()` or `trimmedBody()` rather than treating `isSuccess()` as a
universal API-success indicator.

Those failures are `UnexpectedReplyException`, which remains distinct from
transport failures and parse failures.

---

## bgapi state implications

When a `BgapiCommand` is sent, the connection enters a state where a later
`BACKGROUND_JOB` event is expected with the matching `Job-UUID`.

`esl-core` provides:
- `BgapiAcceptedReply.jobUuid()` — the correlation key
- `BackgroundJobEvent.jobUuid()` — the matching event value

Upper layers are responsible for:
- Maintaining a map of pending job UUIDs
- Matching BACKGROUND_JOB events to pending commands
- Handling job timeout or lost-event scenarios

This package does not implement a correlation registry — that belongs in upper-layer packages.

---

## Event subscription state

Before events are delivered, the client must send:
```
event plain [event-names...]\n\n
```

FreeSWITCH replies with:
```
command/reply
Reply-Text: +OK event listener enabled plain
```

Before this exchange, no `text/event-plain` frames will arrive. The session-state
manager in the upper layer must track whether event subscription is active.

If the client subscribes with JSON format, `text/event-json` frames may arrive
instead. `esl-core` normalizes `text/event-plain` and `text/event-json` through
the same `EventParser` → `NormalizedEvent` → `EventFactory` flow.

---

## Disconnect sequence

```
Client → Server: exit\n\n
Server → Client: (command/reply +OK)
Server → Client: text/disconnect-notice
[connection closed]
```

Or unsolicited:
```
Server → Client: text/disconnect-notice
[connection closed]
```

The `ClassifiedInboundMessage.isDisconnectNotice()` method provides the detection point.
