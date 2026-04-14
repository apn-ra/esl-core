This directory is a quarantine area for live ESL captures produced by:
- `tools/smoke/live_freeswitch_call_flow_validate.php`

These files are operator artifacts, not package API and not stable fixtures.

Use captures for:
- inspecting real wire behavior during live validation
- preserving controlled call-flow event frames for bridge/playback fixture review
- promoting selected samples into curated test fixtures after review

Do not commit ad hoc captures blindly. Review and minimize them before turning any sample into a fixture.
