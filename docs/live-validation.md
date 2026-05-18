# Live Validation

This document describes optional operator-run validation against the repository
Docker FreeSWITCH lab. These checks are not part of default CI.

## Docker FreeSWITCH Lab

The current lab is defined by:

| Setting | Value |
|---|---|
| Compose file | `docker/docker-compose.yml` |
| Service | `lab01-esl-core` |
| Container name | `freeswitch-esl-core` |
| Network | `network_mode: host` |
| ESL endpoint | `127.0.0.1:8021` from the host |
| Password source | `docker/freeswitch/conf/autoload_configs/event_socket.conf.xml` |
| Default password | `ClueCon` |

The Dockerfile installs FreeSWITCH under `/usr/local/freeswitch/bin` and places
that directory on `PATH`, so `fs_cli` should be available inside a built
container. The CUSTOM event live proof below uses ESL commands directly and does
not require `fs_cli`.

## Start The Lab

```bash
docker compose -f docker/docker-compose.yml up -d
```

Because the service uses host networking, the expected host-side ESL endpoint is
`127.0.0.1:8021`.

## Run The CUSTOM Event Live Proof

The test is skipped unless `ESL_CORE_LIVE_TEST=1` is set. It connects to ESL,
authenticates, subscribes to plain events for the short validation window,
sends a neutral `sendevent CUSTOM` frame, and parses the observed event through
`InboundPipeline::withDefaults()`.

The bounded plain-event subscription window is intentional: the Docker lab did
not observe the injected event when filtering only to `CUSTOM`. This validates
that a real ESL CUSTOM event can be received and parsed through public
esl-core APIs. It does not model downstream domain semantics.

```bash
ESL_CORE_LIVE_TEST=1 \
ESL_CORE_LIVE_HOST=127.0.0.1 \
ESL_CORE_LIVE_PORT=8021 \
ESL_CORE_LIVE_PASSWORD=ClueCon \
vendor/bin/phpunit tests/Live/CustomEventLiveTest.php --filter CustomEventLiveTest
```

Optional overrides:

| Env var | Default |
|---|---|
| `ESL_CORE_LIVE_TIMEOUT` | `6` |
| `ESL_CORE_LIVE_DOCKER_COMPOSE_FILE` | `docker/docker-compose.yml` |
| `ESL_CORE_LIVE_DOCKER_SERVICE` | `lab01-esl-core` |

The compose-file and service env vars are documented for operator scripts and
runbooks. The PHPUnit test itself only needs the ESL endpoint and password.

## Stop The Lab

```bash
docker compose -f docker/docker-compose.yml down
```

## Safety

The live CUSTOM proof does not require calls, registration, dialplan execution,
audio, external services, or persistent FreeSWITCH config mutation. If Docker,
ESL, auth, subscription, or neutral event injection is unavailable, the test
skips with a reason instead of becoming part of default verification.
