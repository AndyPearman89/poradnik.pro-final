# Raport testow obciazeniowych /track

- data_utc: 2026-03-20T20:53:47.663Z
- base_url: http://127.0.0.1:8080
- event: cta_click
- source: affiliate
- overall: FAIL

| Scenariusz | Status | OK | Failed | RPS | p95 (ms) | p99 (ms) |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Baseline | FAIL | - | - | - | - | - |
| Peak | FAIL | - | - | - | - | - |

### Baseline

- exit_code: 2
- requests: 500
- concurrency: 15

```text
(no stdout)
```

```text
Endpoint unreachable: http://127.0.0.1:8080/wp-json/peartree/v1/track
Start WordPress locally and rerun this command with --base.
```

### Peak

- exit_code: 2
- requests: 2000
- concurrency: 50

```text
(no stdout)
```

```text
Endpoint unreachable: http://127.0.0.1:8080/wp-json/peartree/v1/track
Start WordPress locally and rerun this command with --base.
```

