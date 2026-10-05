# Centrifugo observability

Centrifugo sits between "the backend published an event" and "the dispatch
board updated", and until now it emitted **nothing** to Datadog — only
`service:symfony` and `service:nginx` ship logs. That gap is why a stale
dispatch board could not be diagnosed from Datadog: every component in the
path was observable except the one doing the delivery.

This adds the two halves.

## 1. Application side (already in the code)

`AppBundle\Service\LiveUpdates` now:

- inspects the Centrifugo API response and logs an **error** when a publication
  is refused. Centrifugo answers `200` with an `error` object in the body, and
  `phpcent` only throws on a non-200, so a refused publication used to be
  indistinguishable from a delivered one;
- appends a compact description of the payload to each publication log, e.g.
  `Broadcasting event 'task:done' [task#331073 status=DONE updatedAt=...]`.
  The status in that line is what tells you whether the payload carried the
  state the dispatcher was waiting for, or a stale one.

These land in the existing `real_time_message` channel, which ships at `info`
in prod. No infrastructure change needed.

Useful queries:

```
env:naofood "Centrifugo refused event"
env:naofood "Broadcasting event" "task:done"
```

## 2. Agent side (needs deploying)

`centrifugo.d/conf.yaml` goes to `/etc/datadog-agent/conf.d/centrifugo.d/conf.yaml`
on the Centrifugo host, then `systemctl restart datadog-agent`.

### Metrics

Nothing to change in Centrifugo: `CENTRIFUGO_PROMETHEUS=true` is already set in
production, so `/metrics` is live. Adjust `openmetrics_endpoint` if the agent
cannot reach Centrifugo on `localhost:8000`.

The metrics worth watching first:

| Metric | Reads as |
|---|---|
| `centrifugo.num_clients` | open connections; one per dispatch board tab |
| `centrifugo.num_connect` / `num_disconnect` | reconnect rate — a high rate with flat `num_clients` means clients are looping |
| `centrifugo.messages_sent` vs `transport_messages_sent` | events accepted from the API vs actually delivered; a gap means Centrifugo dropped them |
| `centrifugo.num_reply_errors` | refused subscribes/publishes |
| `centrifugo.num_recover` | reconnects that replayed missed messages from history |

### Logs

Centrifugo must actually write logs somewhere the agent can read:

```
CENTRIFUGO_LOG_LEVEL: "info"
CENTRIFUGO_LOG_FILE: "/var/log/centrifugo/centrifugo.log"
```

Add those next to the other `CENTRIFUGO_*` variables in the Ansible role, and
make sure the directory exists and is readable by `dd-agent`.

If Centrifugo runs as a container instead, drop the `logs:` block from
`conf.yaml` and let the agent's Docker log collection pick up stdout, with
`com.datadoghq.ad.logs` labels setting `source: centrifugo`.

`debug` is deliberately not suggested: it logs a line per client command and
will swamp the index at production connection counts.
