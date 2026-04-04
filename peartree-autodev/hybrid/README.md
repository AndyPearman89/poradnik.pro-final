# PEARTREE AUTODEV HYBRID (Python + Copilot) v1.0

Cel: pol-autonomiczny tryb pracy task -> implementacja -> commit -> push -> repeat.

## Architektura

- Python: sterowanie petla + git + task planning
- VS Code + Copilot: implementacja kodu z TASK.md
- Work Queue: automatyczne pobieranie zadan z `docs/implementation/autodev-work-queue.md`

## Pliki

- `runner.py` - glowna petla workflow
- `planner.py` - pobieranie nastepnego zadania z work queue
- `TASK.md` - aktualny task dla Copilot (generowany automatycznie)
- `TASKS_QUEUE.md` - pelna lista zadan i instrukcje

## Wymagania

- Python 3.10+
- Git
- VS Code
- GitHub Copilot + Copilot Chat

## Konfiguracja Copilot Chat

Wklej do Copilot Chat przed rozpoczeciem pracy:

```text
You are autonomous senior PHP architect working on poradnik.pro WordPress platform.

RULES:

* Never ask questions - make decisions autonomously
* Always implement code directly in files
* Follow DDD architecture patterns
* PHP 8+ with declare(strict_types=1)
* No placeholders or TODO comments
* Production-ready code only
* Create tests when task requires them

WORKFLOW:

1. Read TASK.md to understand the current task
2. Implement the solution step-by-step
3. Modify project files directly
4. Create tests if specified in acceptance criteria
5. Ensure code follows project conventions

TASK:
Execute the task from TASK.md and modify project files accordingly.
```

## Uruchomienie

### Start petli (continuous mode):

```bash
cd peartree-autodev/hybrid
python runner.py
```

### Single cycle (test mode):

```bash
cd peartree-autodev/hybrid
HYBRID_RUN_ONCE=1 python runner.py
```

## Realny flow pracy

1. **Python runner** czyta `docs/implementation/autodev-work-queue.md`
2. **Python runner** pobiera pierwszy OPEN task
3. **Python runner** zapisuje szczegolowy opis do `TASK.md`
4. **Terminal** wyswietla: "➡️ Otworz TASK.md i wykonaj task w Copilot"
5. **Developer** otwiera `TASK.md` w VS Code
6. **Developer** wkleja prompt Copilot Chat (powyzej)
7. **Copilot Chat** czyta task i implementuje rozwiazanie
8. **Developer** weryfikuje zmiany w plikach
9. **Developer** wraca do terminala i naciska ENTER
10. **Python runner** wykonuje `git add`, `git commit`, `git push`
11. **Petla** powtarza sie dla kolejnego taska

## Bezpieczniki

Runner ma wbudowane zabezpieczenia:

✅ **Brak zmian**: Jesli nie wykryje zmian, pomija commit/push
✅ **Filtrowanie plikow**: Nie commituje runtime files (logs, memory, __pycache__)
✅ **Validation**: Sprawdza czy staged changes sa dozwolone
✅ **Manual control**: Wymaga ENTER przed commit/push
✅ **Single cycle**: Mozna uruchomic jeden cykl z `HYBRID_RUN_ONCE=1`
✅ **Interrupt**: Mozna przerwac CTRL+C w dowolnym momencie

## Work Queue Integration

### Zrodla zadan (w kolejnosci priorytetu):

1. `docs/implementation/autodev-work-queue.md` - OPEN tasks
2. `docs/implementation/final-project-tasklist.md` - [OPEN] i [WIP] tasks
3. Fallback: "Review TODO/FIXME comments in codebase"

### Format work queue:

```markdown
- OPEN: TASK-XXX opis zadania
- DONE: TASK-YYY zadanie zakonczone
- BLOCKED: TASK-ZZZ zadanie zablokowane
```

### Aktualizacja work queue:

Po zakonczeniu taska, manualnie zmien status w pliku:

```bash
# Przed
- OPEN: TASK-C05 zrealizowac monitoring SLA partnerow + alerting

# Po zakonczeniu
- DONE: TASK-C05 zrealizowac monitoring SLA partnerow + alerting
```

## Monitorowanie

### Sprawdz ostatnie commity:

```bash
git log --oneline -10
```

### Sprawdz status git:

```bash
git status
```

### Sprawdz aktualny task:

```bash
cat peartree-autodev/hybrid/TASK.md
```

### Sprawdz kolejke zadan:

```bash
cat peartree-autodev/hybrid/TASKS_QUEUE.md
cat docs/implementation/autodev-work-queue.md
```

## Troubleshooting

### Problem: Runner nie commituje zmian

**Przyczyna**: Brak staged changes lub tylko runtime files

**Rozwiazanie**:
```bash
git status
git diff
# Sprawdz czy zmiany sa w dozwolonych plikach
```

### Problem: Push failed

**Przyczyna**: Branch protection, conflicts, lub brak uprawnien

**Rozwiazanie**:
```bash
git status
git pull --rebase
git push
```

### Problem: Copilot nie implementuje poprawnie

**Przyczyna**: Niejasny task lub brak kontekstu

**Rozwiazanie**:
1. Rozszerz opis w TASK.md
2. Dodaj przykladowy kod w TASK.md
3. Wskazz konkretne pliki do modyfikacji
4. Ponow prompt w Copilot Chat

### Problem: Task za duzy

**Przyczyna**: Task wymaga wielu zmian

**Rozwiazanie**:
1. Podziel task na mniejsze sub-taski
2. Dodaj sub-taski do work queue jako osobne OPEN items
3. Wykonaj po kolei

## Przykladowy cykl pracy

```
=== AUTODEV HYBRID LOOP ===
TASK: TASK-C05 zrealizowac monitoring SLA partnerow + alerting.
➡️ Otworz TASK.md i wykonaj task w Copilot
Nacisnij ENTER po zakonczeniu...

[Developer otwiera TASK.md w VS Code]
[Developer wkleja prompt do Copilot Chat]
[Copilot implementuje class-sla-monitor.php]
[Copilot updatuje class-lead-router.php]
[Copilot tworzy unit-test-sla-monitor.php]
[Developer weryfikuje zmiany]
[Developer naciska ENTER]

Commit + push: OK

=== AUTODEV HYBRID LOOP ===
TASK: Dodac testy wydajnosciowe dla /track endpoint...
➡️ Otworz TASK.md i wykonaj task w Copilot
```

## Ograniczenia

- ❌ Wymaga manualnego ENTER po kazdym tasku
- ❌ Wymaga VS Code + Copilot (nie w pelni autonomiczny)
- ❌ Implementacja kodu jest wykonywana przez Copilot/Developer
- ✅ Python kontroluje workflow i git operations
- ✅ Automatyczne pobieranie zadan z work queue
- ✅ Bezpieczne commit/push z filtrowaniem

## Uwaga praktyczna

Runner ma bezpiecznik: jesli nie ma zmian lub nie ma zmian staged, commit/push jest pomijany.

To zapobiega pustym commitom i commitom z tylko runtime artifacts.

## Porownanie z Fully Autonomous Mode

| Feature | Hybrid Mode | Fully Autonomous |
|---------|-------------|------------------|
| Task planning | Automatyczny | Automatyczny |
| Code implementation | Copilot Chat + Human | AI Agent |
| Review | Human | Validation gates |
| Commit/Push | Automatyczny po ENTER | Automatyczny |
| Rollback | Manual | Manual |
| Safety | Human verification | Automated tests |

## Kiedy uzywac Hybrid Mode?

✅ Rozwoj nowych features wymagajacych human judgment
✅ Refactoring duzych komponentow
✅ Tasks wymagajace kreatywnosci
✅ Prototypowanie i eksperymenty
✅ Learning i onboarding nowych deweloperow

## Kiedy uzywac Fully Autonomous Mode?

✅ Repetitive tasks (formatting, linting)
✅ Bugfixes z jasno okreslonym scope
✅ Tests generation
✅ Documentation updates
✅ Maintenance tasks
