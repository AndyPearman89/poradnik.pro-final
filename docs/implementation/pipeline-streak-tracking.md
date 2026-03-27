# Pipeline Green Streak Tracking (TASK-H02)

## Cel

Potwierdzenie stabilnosci systemu przez 7 kolejnych dni zielonego pipeline CI.
Streak jest uznany za spelniony gdy nightly quality pipeline przechodzi bez bledow przez 7 kolejnych dni kalendarzowych.

## Definicja "zielonego dnia"

Dzien uznawany jest za **zielony** gdy:

1. Workflow `Nightly Quality Pipeline` (`.github/workflows/nightly-quality.yml`) zakonczy sie ze statusem `success`.
2. Wszystkie gate'y pass:
   - FE smoke test
   - Lead form integration test
   - JS no-jQuery regression suite
   - Visual smoke test
   - a11y audit
   - KPI summary + dashboard E2E
   - PHP unit tests
   - Load test + SLO gate (p95 ≤ 2000ms, p99 ≤ 5000ms)

## Narzedzie do sledzenia

Skrypt: `scripts/track-pipeline-streak.mjs`
Stan streak: `docs/implementation/reports/pipeline-streak.json`

### Rejestrowanie wyniku dnia

```bash
# Gdy pipeline ZIELONY (wszystkie testy pass):
node scripts/track-pipeline-streak.mjs --result pass

# Gdy pipeline CZERWONY (jakikolwiek test fail):
node scripts/track-pipeline-streak.mjs --result fail

# Backfill konkretnego dnia (YYYY-MM-DD):
node scripts/track-pipeline-streak.mjs --result pass --date 2026-03-21
```

### Sprawdzenie aktualnego streak

```bash
# Sprawdz czy streak >= 7 (cel):
node scripts/track-pipeline-streak.mjs --check --threshold 7

# Wyswietl pelna historie:
node scripts/track-pipeline-streak.mjs --history

# Sprawdz status bez zmian:
node scripts/track-pipeline-streak.mjs
```

### Przyklad wyjscia

```
╔══════════════════════════════════════════╗
║     Pipeline Green Streak Tracker        ║
╚══════════════════════════════════════════╝
  Current streak : 5 / 7 days 🔄 in progress
  Longest streak : 5 days
  Last recorded  : 2026-03-25
  Progress       : █████░░░░░ 71%
  Remaining      : 2 more consecutive green day(s) needed
  Total entries  : 12
```

## Integracja z CI (opcjonalna automatyzacja)

Mozna dodac krok do nightly pipeline, ktory automatycznie rejestruje wynik:

```yaml
# W .github/workflows/nightly-quality.yml
- name: Record pipeline streak result
  if: always()
  run: |
    RESULT="fail"
    if [[ "${{ job.status }}" == "success" ]]; then
      RESULT="pass"
    fi
    node scripts/track-pipeline-streak.mjs --result $RESULT

- name: Check 7-day streak gate
  run: node scripts/track-pipeline-streak.mjs --check --threshold 7
```

> **Uwaga**: Krok `--check` nalezy uruchamiac tylko w kontekscie release readiness check,
> nie jako blokujacy gate codzienny. Streak blokuje tylko release (TASK-H05), nie deploy patch.

## Definicja ukonczenia TASK-H02

TASK-H02 jest DONE gdy:

1. `pipeline-streak.json` zawiera ≥ 7 kolejnych wpisow `pass`.
2. Komenda `node scripts/track-pipeline-streak.mjs --check --threshold 7` zwraca exit code 0.
3. Zarowno streak jak i historia sa widoczne w raporcie.

## Krok po kroku – procedura weryfikacji

| Krok | Akcja | Oczekiwany rezultat |
|------|-------|---------------------|
| 1 | Uruchom nightly pipeline recznie (`workflow_dispatch`) | Pipeline green |
| 2 | `node scripts/track-pipeline-streak.mjs --result pass` | Streak +1 |
| 3 | Powtarzaj kroki 1-2 przez 7 dni | Streak = 7 |
| 4 | `node scripts/track-pipeline-streak.mjs --check` | Exit 0, "TARGET MET" |
| 5 | Zaktualizuj tasklist: TASK-H02 → DONE | — |

## Resetowanie streak

Streak resetuje sie do 0 po kazdym `--result fail` lub przerwie w kolejnosci dat (gap > 1 dzien).
Aby zapobiec falszywym przerwom podczas weekendow lub dni bez deployu:
- Rejestruj wynik KAZDEGO dnia operacyjnego.
- W dniach bez zmian mozna polegac na nightly cron (automatyczny trigger).

## Plik stanu

Plik `docs/implementation/reports/pipeline-streak.json` jest commitowany do repo i sluzy jako source of truth.

Przykladowa zawartosc po 3 zielonych dniach:

```json
{
  "streak": 3,
  "longestStreak": 3,
  "lastDate": "2026-03-23",
  "history": [
    { "date": "2026-03-21", "result": "pass" },
    { "date": "2026-03-22", "result": "pass" },
    { "date": "2026-03-23", "result": "pass" }
  ]
}
```
