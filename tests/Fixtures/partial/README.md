# Partial Fixture Provenance

These fixtures are intentionally incomplete raw ESL byte strings. They must not
contain inline comments because comments would become parser input and change
the partial/truncation condition being tested.

| Fixture | Provenance | Purpose |
|---|---|---|
| `api-response-body-truncated-partial.bin` | constructed protocol corpus | Proves a body shorter than the declared 12-byte `Content-Length` buffers until `finish()` reports truncation. |
| `auth-request-missing-terminator-partial.bin` | constructed protocol corpus | Proves a header block without the terminating blank line buffers until `finish()` reports truncation. |
