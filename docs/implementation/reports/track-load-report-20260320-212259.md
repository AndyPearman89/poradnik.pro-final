# Raport testow obciazeniowych /track

- data_utc: 2026-03-20T21:22:59.927Z
- base_url: http://127.0.0.1:8080
- event: cta_click
- source: affiliate
- overall: PASS

| Scenariusz | Status | OK | Failed | RPS | p95 (ms) | p99 (ms) |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| Baseline | PASS | 500 | 0 | 17.22 | 1128 | 3318 |
| Peak | PASS | 2000 | 0 | 17.43 | 3226 | 4263 |

### Baseline

- exit_code: 0
- requests: 500
- concurrency: 15

```text
Load test result
endpoint: http://127.0.0.1:8080/wp-json/peartree/v1/track
requests: 500
concurrency: 15
ok: 500
failed: 0
duration_ms: 29035
rps: 17.22
latency_avg_ms: 868.16
latency_p50_ms: 825
latency_p95_ms: 1128
latency_p99_ms: 3318
statuses: 200:500
```

```text
(no stderr)
```

### Peak

- exit_code: 0
- requests: 2000
- concurrency: 50

```text
Load test result
endpoint: http://127.0.0.1:8080/wp-json/peartree/v1/track
requests: 2000
concurrency: 50
ok: 2000
failed: 0
duration_ms: 114765
rps: 17.43
latency_avg_ms: 2854.55
latency_p50_ms: 2852
latency_p95_ms: 3226
latency_p99_ms: 4263
statuses: 200:2000
```

```text
(no stderr)
```

