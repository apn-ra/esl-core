# Live Fixture Provenance

This document records the provenance of curated live fixtures promoted from
quarantined FreeSWITCH ESL captures.

It exists to keep the release story conservative and traceable:

- the package capability claim comes from the curated fixture plus its contract
  test
- the raw source of truth is the quarantined ESL capture under
  `tools/smoke/captures/`
- the capture producer is non-public operator tooling under `tools/smoke/`
- operator setup and PBX behavior are prerequisites for capture generation, not
  package API

The primary truth for these fixture promotions is the raw ESL frame captured by
the helper, not MCP telemetry or any external observability layer. MCP-assisted
inspection may help operators during a live run, but the promoted fixture must
round-trip to a quarantined raw frame in this repository.

## Controlled Scenario

All fixtures in this table came from the same non-public controlled loopback
call-flow scenario:

- helper: `tools/smoke/live_freeswitch_call_flow_validate.php`
- dialplan: `tools/smoke/freeswitch/apn-esl-core-smoke.xml`
- scenario: loopback call into `apn-esl-core-events`, tone-stream playback on
  the inbound leg, then bridge to a loopback peer leg
- teardown: the helper observes `CHANNEL_BRIDGE`, then performs a controlled
  `uuid_kill` of the observed peer leg so `CHANNEL_UNBRIDGE` is emitted
  deterministically inside the capture window

## Audited Fixtures

| Fixture | Source Capture | Mode | Why Promoted | Contract Test Coverage |
|---|---|---|---|---|
| `tests/Fixtures/live/events/channel-bridge-loopback-json.esl` | `tools/smoke/captures/20260414T070855Z-call-flow-json-016-full-frame-7da7a950.esl` | `json` | Live-backed bridge fixture for the controlled loopback scenario; proves `CHANNEL_BRIDGE` in `text/event-json` decodes to `BridgeEvent` with the expected bridge peer identifiers | `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_channel_bridge_json_fixture_preserves_bridge_identifiers()` |
| `tests/Fixtures/live/events/channel-unbridge-loopback-json.esl` | `tools/smoke/captures/20260414T070856Z-call-flow-json-019-full-frame-a2704145.esl` | `json` | Live-backed unbridge fixture for the same controlled scenario; proves the controlled teardown yields `CHANNEL_UNBRIDGE` in `text/event-json` and that it stays inside the existing `BridgeEvent` family | `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_channel_unbridge_json_fixture_preserves_bridge_identifiers()` |
| `tests/Fixtures/live/events/playback-start-tone-stream-json.esl` | `tools/smoke/captures/20260414T070855Z-call-flow-json-009-full-frame-8cd0c2e0.esl` | `json` | Live-backed playback fixture for the tone-stream leg; proves `PLAYBACK_START` in `text/event-json` decodes to `PlaybackEvent` with the expected playback path | `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_playback_start_json_fixture_preserves_playback_context()` |
| `tests/Fixtures/live/events/playback-stop-tone-stream-json.esl` | `tools/smoke/captures/20260414T070855Z-call-flow-json-011-full-frame-a4086769.esl` | `json` | Live-backed playback completion fixture for the tone-stream leg; proves `PLAYBACK_STOP` in `text/event-json` decodes to `PlaybackEvent` and preserves completion status | `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_playback_stop_json_fixture_preserves_playback_context()` |
| `tests/Fixtures/live/events/background-job-originate-ok-json.esl` | `tools/smoke/captures/20260414T070855Z-call-flow-json-010-full-frame-5c316866.esl` | `json` | Live-backed successful `BACKGROUND_JOB` completion for the controlled originate; kept because it adds a positive live JSON job-result path alongside the existing failure fixture | `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_promoted_json_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowJsonFixtureTest::test_background_job_json_fixture_preserves_success_body_and_job_correlation()` |
| `tests/Fixtures/live/events/background-job-no-route-destination-plain.esl` | `tools/smoke/captures/20260414T062140Z-call-flow-plain-012-full-frame-6fe70db4.esl` | `plain` | Live-backed failure-path `BACKGROUND_JOB` fixture from the earlier loopback originate attempt before the target dialplan route was installed; kept because it documents the honest FreeSWITCH failure result without widening the typed event model | `tests/Contract/Events/LiveBackgroundJobFailureFixtureTest::test_fixture_classifies_as_event_message()`, `tests/Contract/Events/LiveBackgroundJobFailureFixtureTest::test_factory_produces_background_job_event()`, `tests/Contract/Events/LiveBackgroundJobFailureFixtureTest::test_result_body_is_preserved_from_wire()` |
| `tests/Fixtures/live/events/channel-bridge-loopback-plain.esl` | `tools/smoke/captures/20260414T071652Z-call-flow-plain-016-full-frame-57a2f333.esl` | `plain` | Plain-mode bridge counterpart to the proven JSON fixture; proves `CHANNEL_BRIDGE` in `text/event-plain` decodes to `BridgeEvent` and preserves URL-decoded normalized headers | `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_channel_bridge_plain_fixture_preserves_bridge_identifiers()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_correlation_and_replay_metadata_remain_protocol_truthful()` |
| `tests/Fixtures/live/events/channel-unbridge-loopback-plain.esl` | `tools/smoke/captures/20260414T071653Z-call-flow-plain-019-full-frame-d12cfd8d.esl` | `plain` | Plain-mode unbridge counterpart to the proven JSON fixture; proves `CHANNEL_UNBRIDGE` in `text/event-plain` decodes to `BridgeEvent` with the same bridge peer identifiers | `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_channel_unbridge_plain_fixture_preserves_bridge_identifiers()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_correlation_and_replay_metadata_remain_protocol_truthful()` |
| `tests/Fixtures/live/events/playback-start-tone-stream-plain.esl` | `tools/smoke/captures/20260414T071652Z-call-flow-plain-009-full-frame-509c10ca.esl` | `plain` | Plain-mode playback-start counterpart; proves `PLAYBACK_START` decodes through the URL-decoding path and yields the expected normalized playback file path | `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_playback_start_plain_fixture_decodes_url_encoded_playback_context()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_correlation_and_replay_metadata_remain_protocol_truthful()` |
| `tests/Fixtures/live/events/playback-stop-tone-stream-plain.esl` | `tools/smoke/captures/20260414T071652Z-call-flow-plain-011-full-frame-cede70df.esl` | `plain` | Plain-mode playback-stop counterpart; proves `PLAYBACK_STOP` decodes through the URL-decoding path, preserves playback status, and remains inside the current `PlaybackEvent` family | `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_parses_classifies_and_decodes_to_expected_type()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_playback_stop_plain_fixture_decodes_url_encoded_playback_context()`, `tests/Contract/Events/LiveCallFlowPlainFixtureTest::test_promoted_plain_fixture_correlation_and_replay_metadata_remain_protocol_truthful()` |

## Audit Outcome

All audited live fixtures in the current bridge/playback promotion set now have:

- one exact raw source capture in `tools/smoke/captures/`
- one identified capture mode (`plain` or `json`)
- one controlled operator scenario
- one explicit promotion reason
- one or more contract tests that now pin the behavior

This is sufficient traceability for the current pre-`1.0.0` milestone without
introducing new package surface or a larger provenance framework.
