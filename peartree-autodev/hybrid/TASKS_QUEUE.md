# Hybrid Copilot Tasks Queue

## Instrukcje dla Copilot

Gdy otworzysz TASK.md z aktualnym zadaniem, wykonaj je krok po kroku:

### Zasady pracy

1. **Nie zadawaj pytan** - podejmuj decyzje samodzielnie
2. **Implementuj bezposrednio w plikach** - nie pokazuj tylko kodu
3. **Stosuj architekture DDD** - Domain-Driven Design
4. **PHP 8+ z strict_types=1** - zawsze na poczatku pliku
5. **Bez placeholderow** - tylko produkcyjny kod
6. **Testy razem z kodem** - jesli task wymaga testow

### Konfiguracja Copilot Chat

```
You are autonomous senior PHP architect working on poradnik.pro WordPress platform.

RULES:
* Never ask questions - make decisions autonomously
* Always implement code directly in files
* Follow DDD architecture patterns
* PHP 8+ with declare(strict_types=1)
* No placeholders or TODO comments
* Production-ready code only
* Create tests when needed

TASK:
Execute the task from TASK.md step-by-step and modify project files accordingly.
```

---

## Kolejka zadan (OPEN)

Zadania ponizej beda automatycznie pobierane przez runner.py:

### Z autodev-work-queue.md:
- TASK-C05 zrealizowac monitoring SLA partnerow + alerting

### Kolejne zadania do rozbudowy:

- Dodac testy wydajnosciowe dla /track endpoint z obciazeniem 1000 req/s
- Zaimplementowac cache layer dla KPI dashboard (Redis)
- Dodac monitoring i alerting dla slow queries w MySQL
- Rozbudowac eksport CSV o dodatkowe formaty (Excel, JSON)
- Dodac rate limiting per IP dla /track endpoint
- Zaimplementowac backup scheduler dla bazy danych
- Dodac health check endpoint dla monitoringu produkcyjnego
- Rozbudowac structured data o dodatkowe schema.org typy
- Dodac A/B testing framework dla CTA variants
- Zaimplementowac email notifications dla lead routing failures

---

## Zadania wykonane (DONE)

Zadania ponizej sa juz zakonczone i nie beda ponawiane:

- ✅ TASK-B01 E2E HTTP dashboard KPI tie-order (multiday)
- ✅ TASK-G01 nightly pipeline (smoke + unit + integration + load + raport)
- ✅ TASK-G03 release runbook (preflight/deploy/rollback/post-deploy)
- ✅ TASK-G05 checklist incydentowa /track i lead submit
- ✅ TASK-A01 idempotent bootstrap WP (CI + local)
- ✅ TASK-A05 hard gate runtime errors w FE smoke
- ✅ TASK-B02 test kontraktu export CSV dla 365 dni
- ✅ TASK-B03 walidacja schematu payload /track (allowlist)
- ✅ TASK-B04 regresja retention 14/365
- ✅ TASK-B05 metryka invalid payload count w KPI
- ✅ TASK-C01 E2E lead flow end-to-end
- ✅ TASK-C02 retry/backoff scenariusze API lead
- ✅ TASK-C03 routing multi/exclusive + testy kontraktu
- ✅ TASK-C04 antyspam honeypot + throttle integration tests
- ✅ TASK-E01 ranking premium weighting tests
- ✅ TASK-E02 disclosure + affiliate->lead fallback validation
- ✅ TASK-E03 A/B CTA eksperyment + raport
- ✅ TASK-E04 ads density vs CTA visibility tests (mobile)
- ✅ TASK-E05 revenue mix dashboard per page type
- ✅ TASK-F01 search UX interaction tests
- ✅ TASK-F02 Lighthouse mobile gate + trend
- ✅ TASK-H02 7 kolejnych dni zielonego pipeline
- ✅ TASK-H03 target metryk produkcyjnych (CR/EPC/RPM/CWV)
- ✅ TASK-H05 final release tag i freeze zmian krytycznych

---

## Workflow

1. **Python runner** zapisuje nastepny task z kolejki do `TASK.md`
2. **Developer** otwiera `TASK.md` w VS Code
3. **Copilot Chat** otrzymuje prompt i implementuje task
4. **Developer** weryfikuje zmiany i naciska ENTER w terminalu
5. **Python runner** robi `git add`, `git commit`, `git push`
6. **Petla** powtarza sie dla kolejnego taska

---

## Bezpieczniki

Runner ma wbudowane bezpieczniki:

- ✅ Nie commituje jesli brak zmian
- ✅ Nie commituje plikow runtime (logs, memory, __pycache__)
- ✅ Nie pushuje jesli commit failed
- ✅ Mozna przerwac CTRL+C w dowolnym momencie
- ✅ Mozna uruchomic single-cycle z `HYBRID_RUN_ONCE=1`

---

## Uzywanie

### Start petli:
```bash
cd peartree-autodev/hybrid
python runner.py
```

### Single cycle (test):
```bash
cd peartree-autodev/hybrid
HYBRID_RUN_ONCE=1 python runner.py
```

### Monitorowanie:
```bash
# W innym terminalu
tail -f peartree-autodev/logs/runner.log.jsonl
git log --oneline -10
```
