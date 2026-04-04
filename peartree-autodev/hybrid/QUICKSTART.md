# Hybrid Copilot Workflow - Quick Start Guide

## Wymagania

- [x] Python 3.10+
- [x] Git skonfigurowany
- [x] VS Code zainstalowany
- [x] GitHub Copilot + Copilot Chat aktywowany

## Krok 1: Konfiguracja Copilot Chat

Otwórz VS Code i przygotuj ten prompt w Copilot Chat:

```
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

**Nie uruchamiaj jeszcze!** Tylko przygotuj prompt.

## Krok 2: Uruchom Runner

W terminalu:

```bash
cd /home/runner/work/poradnik.pro-final/poradnik.pro-final/peartree-autodev/hybrid
python runner.py
```

## Krok 3: Workflow

Zobaczysz:

```
=== AUTODEV HYBRID LOOP ===
TASK: TASK-C05 zrealizowac monitoring SLA partnerow + alerting.
➡️ Otworz TASK.md i wykonaj task w Copilot
Nacisnij ENTER po zakonczeniu...
```

## Krok 4: Implementacja w VS Code

1. Otwórz plik `TASK.md` w VS Code
2. Przeczytaj szczegóły zadania
3. Wklej przygotowany prompt do Copilot Chat
4. Poczekaj aż Copilot zaimplementuje rozwiązanie
5. Sprawdź zmodyfikowane pliki (Git panel w VS Code)
6. Zweryfikuj poprawność kodu

## Krok 5: Commit & Push

1. Wróć do terminala
2. Naciśnij **ENTER**
3. Runner wykona automatycznie:
   - `git add` (tylko dozwolone pliki)
   - `git commit -m "feat: [opis taska]"`
   - `git push`

## Krok 6: Następny Task

Runner automatycznie:
1. Pobierze kolejny OPEN task z work queue
2. Zapisze go do TASK.md
3. Poczeka na Twoją implementację
4. Powtórzy cykl

## Przerwanie

Naciśnij **CTRL+C** w terminalu w dowolnym momencie.

## Test Mode (Pojedynczy Cykl)

Jeśli chcesz przetestować tylko jeden task:

```bash
cd peartree-autodev/hybrid
HYBRID_RUN_ONCE=1 python runner.py
```

Po wykonaniu jednego taska runner się zatrzyma.

## Monitorowanie

### Sprawdź aktualny task:
```bash
cat TASK.md
```

### Sprawdź kolejkę zadań:
```bash
cat TASKS_QUEUE.md
cat ../../docs/implementation/autodev-work-queue.md
```

### Sprawdź ostatnie commity:
```bash
git log --oneline -5
```

## Troubleshooting

### "No changes detected"

**Przyczyna**: Copilot nie zmodyfikował żadnych plików lub tylko runtime files.

**Rozwiązanie**: 
- Sprawdź `git status`
- Upewnij się że Copilot rzeczywiście zapisał zmiany
- Powtórz prompt w Copilot Chat

### "Push failed"

**Przyczyna**: Konflikt lub brak uprawnień.

**Rozwiązanie**:
```bash
git status
git pull --rebase
git push
```

### Copilot nie rozumie taska

**Rozwiązanie**:
1. Edytuj TASK.md i dodaj więcej szczegółów
2. Wskaż konkretne pliki do modyfikacji
3. Dodaj przykładowy kod
4. Ponów prompt

## Następne Kroki

Po zakończeniu taska:

1. Sprawdź czy został wykonany: `git log -1`
2. Zweryfikuj czy przeszły testy (jeśli są)
3. Zaktualizuj work queue (zmień OPEN na DONE)
4. Kontynuuj z następnym taskiem

## Work Queue Update

Po zakończeniu taska, ręcznie zaktualizuj status:

```bash
# Edytuj plik
vim ../../docs/implementation/autodev-work-queue.md

# Zmień
- OPEN: TASK-C05 zrealizowac monitoring SLA partnerow + alerting

# Na
- DONE: TASK-C05 zrealizowac monitoring SLA partnerow + alerting
```

To zapewni, że następny task będzie pobrany poprawnie.

## Gotowe!

Teraz jesteś gotowy do pracy w trybie Hybrid Copilot Workflow! 🚀

Powodzenia!
