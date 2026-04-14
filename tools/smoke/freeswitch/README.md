# FreeSWITCH Live Event Smoke Flow

This directory contains non-public operator artifacts for live ESL validation.
They are not package API and should not be installed on production dialplans.

## Purpose

`apn-esl-core-smoke.xml` defines the smallest controlled call flow currently
used to produce these live events:

- `PLAYBACK_START`
- `PLAYBACK_STOP`
- `CHANNEL_BRIDGE`
- `CHANNEL_UNBRIDGE`

The flow uses only a local `loopback` endpoint and `tone_stream` playback. It
does not require registered SIP users or an external carrier.

The validation helper observes `CHANNEL_BRIDGE`, then issues a controlled
`uuid_kill` against the bridge peer leg after a short delay. This keeps the
flow reversible and makes `CHANNEL_UNBRIDGE` emission deterministic inside the
helper's observation window. Use `--no-controlled-bridge-teardown` only when
you intentionally want passive observation of the PBX/dialplan behavior.

## Operator Install

Apply only on a non-production FreeSWITCH target.

1. Copy `tools/smoke/freeswitch/apn-esl-core-smoke.xml` to the target's
   dialplan include directory, for example:

   ```bash
   sudo cp tools/smoke/freeswitch/apn-esl-core-smoke.xml /usr/local/freeswitch/conf/dialplan/apn-esl-core-smoke.xml
   ```

2. Reload XML:

   ```bash
   fs_cli -x 'reloadxml'
   ```

3. Run the local capture and trigger helper while the ESL password is available:

   ```bash
   php tools/smoke/live_freeswitch_call_flow_validate.php 38.107.174.40 8021 \
     --password-env=FS_ESL_PASS_FS1 \
     --format=plain \
     --timeout=20 \
     --capture-dir=tools/smoke/captures
   ```

4. Optional JSON-mode pass:

   ```bash
   php tools/smoke/live_freeswitch_call_flow_validate.php 38.107.174.40 8021 \
     --password-env=FS_ESL_PASS_FS1 \
     --format=json \
     --timeout=20 \
     --capture-dir=tools/smoke/captures
   ```

## Teardown

Remove the installed XML file and reload XML:

```bash
sudo rm /usr/local/freeswitch/conf/dialplan/apn-esl-core-smoke.xml
fs_cli -x 'reloadxml'
```

## Expected Sequence

The exact interleaving may include additional channel lifecycle and background
job frames, but a passing run must observe all target names at least once:

1. `PLAYBACK_START`
2. `PLAYBACK_STOP`
3. `CHANNEL_BRIDGE`
4. `CHANNEL_UNBRIDGE`

The helper writes every complete inbound frame to `tools/smoke/captures` when
`--capture-dir` is provided. Those captures are fixture candidates only after
manual review and minimization.
