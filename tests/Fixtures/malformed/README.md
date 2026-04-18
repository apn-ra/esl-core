# Malformed Fixture Provenance

These fixtures are intentionally invalid raw ESL byte strings. They must not
contain inline comments because comments would become parser input and change
the malformed condition being tested.

| Fixture | Provenance | Purpose |
|---|---|---|
| `empty-header-name.esl` | constructed regression | Proves a colon-bearing line with no header name is still malformed. |
| `event-plain-inner-body-truncated.esl` | constructed protocol corpus | Proves a `text/event-plain` payload with inner `Content-Length` larger than the available event body is rejected. |
| `header-name-with-surrounding-whitespace.esl` | constructed protocol corpus | Proves a header name with surrounding whitespace is rejected rather than accepted as a distinct key. |
| `invalid-content-length.esl` | constructed protocol corpus | Proves non-numeric outer `Content-Length` is rejected. |
| `missing-header-colon.esl` | constructed protocol corpus | Proves header lines without a colon separator are rejected. |
