# Autodev Work Queue

Master tasklist: docs/implementation/final-project-tasklist.md

- DONE: przygotowac szkic E2E HTTP testu dashboardu KPI dla tie-order top_sources na danych wielodniowych.
- DONE: utworzyc skrypt scripts/integration-test-kpi-summary.mjs i sprawdzic marker kontraktu KPI summary multi-day tie-order.
- DONE: TASK-B01 wykonac pelny E2E HTTP test dashboardu KPI dla tie-order top_sources (dane wielodniowe).
- DONE: TASK-G01 uruchomic nightly pipeline (smoke + unit + integration + load + raport).
- DONE: TASK-G03 dopisac release runbook: preflight, deploy, rollback, post-deploy checks.
- BLOCKED: TASK-G04 wymaga ustawien branch protection i required checks bezposrednio w GitHub repo settings.
- DONE: TASK-G05 przygotowac checklist incydentowa dla awarii /track i lead submit.
- DONE: TASK-A01 wdrozyc idempotent bootstrap WP dla CI i local.
- DONE: TASK-A05 dodac twardy gate runtime errors w FE smoke.
- DONE: TASK-B02 dodac test kontraktu export CSV dla 365 dni.
- DONE: TASK-B03 dodac walidacje schematu payload /track (allowlist).
- DONE: TASK-B04 dodac regresje retention 14/365.
- DONE: TASK-B05 dodac metryke invalid payload count w KPI.
- DONE: TASK-C01 uruchomic E2E lead flow end-to-end.
- DONE: TASK-C02 dodac retry/backoff scenariusze API lead.
- DONE: TASK-C03 zrealizowac routing multi/exclusive + testy kontraktu.
- DONE: TASK-C04 dodac antyspam honeypot + throttle integration tests.
- OPEN: TASK-C05 zrealizowac monitoring SLA partnerow + alerting.
