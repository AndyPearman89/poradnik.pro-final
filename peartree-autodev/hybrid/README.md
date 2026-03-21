# PEARTREE AUTODEV HYBRID (Python + Copilot) v1.0

Cel: pol-autonomiczny tryb pracy task -> implementacja -> commit -> push -> repeat.

## Architektura

- Python: sterowanie petla + git
- VS Code + Copilot: implementacja kodu z TASK.md

## Pliki

- runner.py
- planner.py
- TASK.md

## Wymagania

- Python 3.10+
- Git
- VS Code
- GitHub Copilot + Copilot Chat

## Konfiguracja Copilot Chat

Wklej do Copilot Chat:

```text
You are autonomous senior PHP architect working on PearTree Core.

RULES:

* Never ask questions
* Always implement code directly in files
* Follow DDD architecture
* PHP 8+, strict_types=1
* No placeholders
* Production-ready code only

TASK:
Execute tasks step-by-step and modify project files.
```

## Uruchomienie

```bash
cd peartree-autodev/hybrid
python runner.py
```

## Realny flow pracy

1. Python zapisuje task do TASK.md.
2. Otwierasz TASK.md i realizujesz task z Copilot.
3. Wracasz do terminala i naciskasz ENTER.
4. Runner robi git add, commit i push.
5. Petla przechodzi do kolejnego taska.

## Ograniczenia

- Wymaga manualnego ENTER.
- Implementacja kodu jest wykonywana przez Copilot/manualnie.

## Uwaga praktyczna

Runner ma bezpiecznik: jesli nie ma zmian lub nie ma zmian staged, commit/push jest pomijany.
