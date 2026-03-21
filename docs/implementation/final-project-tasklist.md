# Final Project Tasklist

## Status Legend

- OPEN: zaplanowane
- WIP: w realizacji
- BLOCKED: zablokowane zaleznoscia
- DONE: zakonczone i zwalidowane

## Priorytet P0 (stabilnosc i release readiness)

- [DONE] TASK-B01 - E2E HTTP dashboard KPI tie-order (multiday)
- [DONE] TASK-G01 - nightly pipeline (smoke + unit + integration + load + raport)
- [DONE] TASK-G03 - release runbook (preflight/deploy/rollback/post-deploy)
- [BLOCKED] TASK-G04 - branch protection i required checks dla main
- [DONE] TASK-G05 - checklist incydentowa /track i lead submit
- [DONE] TASK-A01 - idempotent bootstrap WP (CI + local)
- [DONE] TASK-A05 - hard gate runtime errors w FE smoke

## Priorytet P1 (jakosc danych i konwersja)

- [DONE] TASK-B02 - test kontraktu export CSV dla 365 dni
- [DONE] TASK-B03 - walidacja schematu payload /track (allowlist)
- [DONE] TASK-B04 - regresja retention 14/365
- [DONE] TASK-B05 - metryka invalid payload count w KPI
- [DONE] TASK-C01 - E2E lead flow end-to-end
- [DONE] TASK-C02 - retry/backoff scenariusze API lead
- [DONE] TASK-C03 - routing multi/exclusive + testy kontraktu
- [DONE] TASK-C04 - antyspam honeypot + throttle integration tests
- [DONE] TASK-C05 - monitoring SLA partnerow + alerting

## Priorytet P2 (SEO, UX, monetyzacja)

- [DONE] TASK-D01 - generator local pages (canonical/meta/schema)
- [DONE] TASK-D02 - structured data auto-test
- [DONE] TASK-D03 - internal linking depth controller
- [DONE] TASK-D04 - render tests single-question/archive-local/single-ranking
- [DONE] TASK-D05 - thin-content risk gate
- [DONE] TASK-E01 - ranking premium weighting tests
- [DONE] TASK-E02 - disclosure + affiliate->lead fallback validation
- [DONE] TASK-E03 - A/B CTA eksperyment + raport
- [DONE] TASK-E04 - ads density vs CTA visibility tests (mobile)
- [DONE] TASK-E05 - revenue mix dashboard per page type
- [DONE] TASK-F01 - search UX interaction tests
- [DONE] TASK-F02 - Lighthouse mobile gate + trend
- [OPEN] TASK-F03 - JS no-jQuery regression suite
- [OPEN] TASK-F04 - visual smoke for hero/sections/CTA
- [OPEN] TASK-F05 - a11y audit forms/navigation
## Priorytet P3 (domkniecie programu)

- [OPEN] TASK-H01 - wszystkie taski A-G w DONE
- [OPEN] TASK-H02 - 7 kolejnych dni zielonego pipeline
- [OPEN] TASK-H03 - target metryk produkcyjnych (CR/EPC/RPM/CWV)
- [OPEN] TASK-H04 - finalizacja dokumentacji i przekazanie runbookow
- [OPEN] TASK-H05 - final release tag i freeze zmian krytycznych

## Kolejnosc wykonania (najblizszy sprint)

- 1) TASK-G03
- 2) TASK-G05
- 3) TASK-C03
- 4) TASK-C04
- 5) TASK-C05
