# Testy obciazeniowe endpointu /track

## Cel

Sprawdzic wydajnosc i stabilnosc endpointu telemetryki:

- POST /wp-json/peartree/v1/track

## Narzedzie

Skrypt Node bez zaleznosci zewnetrznych:

- scripts/load-test-track.mjs
- scripts/run-track-load-suite.mjs (baseline + peak + raport markdown)
- scripts/runnee.mjs (autodetekcja base URL + uruchomienie suite)

Wymagania:

- Node 18+
- uruchomiony lokalnie WordPress z motywem poradnik.pro

## Przyklad uruchomienia

```bash
node scripts/load-test-track.mjs \
  --base http://localhost:8080 \
  --requests 1000 \
  --concurrency 25 \
  --event cta_click \
  --source affiliate
```

## Metryki raportowane przez skrypt

- liczba zapytan, concurrency
- liczba sukcesow i bledow
- calkowity czas i RPS
- latency avg, p50, p95, p99
- rozklad kodow HTTP

## Proponowane progi akceptacji (MVP)

- success rate >= 99%
- p95 <= 350 ms
- p99 <= 700 ms
- brak timeoutow i brak 5xx przy obciazeniu testowym

## Scenariusze

1. Smoke:

```bash
node scripts/load-test-track.mjs --base http://localhost:8080 --requests 100 --concurrency 5
```

2. Baseline:

```bash
node scripts/load-test-track.mjs --base http://localhost:8080 --requests 500 --concurrency 15
```

3. Peak:

```bash
node scripts/load-test-track.mjs --base http://localhost:8080 --requests 2000 --concurrency 50
```

## Suite runner (zalecane)

Jednym poleceniem uruchamia baseline i peak oraz zapisuje raport do katalogu docs/implementation/reports:

```bash
node scripts/run-track-load-suite.mjs --base http://localhost:8080
```

Efekt:

- plik raportu: docs/implementation/reports/track-load-report-YYYYMMDD-HHMMSS.md
- tabela wynikow (OK/Failed/RPS/p95/p99)
- surowe logi stdout/stderr dla obu scenariuszy

## Runnee (autonomiczny entrypoint)

Najprostsza opcja: jedna komenda z autodetekcja lokalnego URL WordPress.

```bash
node scripts/runnee.mjs
```

Opcjonalnie mozna wymusic URL:

```bash
node scripts/runnee.mjs --base http://localhost:8080
```

## Uwaga o srodowisku dev container

W tym kontenerze endpoint moze byc niedostepny, jesli lokalny WordPress nie jest uruchomiony. Skrypt zwroci wtedy blad "Endpoint unreachable" i kod wyjscia 2.

## Ostatni zweryfikowany wynik

Run suite wykonany 2026-03-20 na lokalnym Docker WP:

- overall: PASS
- Baseline: 500 req, 0 failed, RPS 17.22, p95 1128 ms, p99 3318 ms
- Peak: 2000 req, 0 failed, RPS 17.43, p95 3226 ms, p99 4263 ms

Raport:

- docs/implementation/reports/track-load-report-20260320-212259.md
