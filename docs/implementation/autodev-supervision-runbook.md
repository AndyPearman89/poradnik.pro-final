# Autodev Supervision Runbook

## Cel

Utrzymac tryb pracy "Senior Dev Agent + Human Controller":

- agent koduje i wykonuje walidacje w petli 24/7,
- kontroler robi review, dokumentuje wynik i decyduje o push,
- produkcyjne push idzie po przejsciu bramek jakosci.

## Tryb pracy

- Runner: `python peartree-autodev/agent/runner.py`
- Config: `peartree-autodev/config/agent.yaml`
- Ustawienie kontroli: `"push": false`

W tym trybie agent moze commitowac lokalnie, ale nie wypycha zmian bez nadzoru.

## Petla nadzorcza

1. Sprawdz status procesu:
   - `pgrep -af "python agent/runner.py"`
2. Sprawdz ostatnie cykle:
   - `tail -n 3 peartree-autodev/logs/runner.log.jsonl`
3. Przejrzyj diff ostatniego commita agenta:
   - `git show --stat --name-only --oneline <sha>`
4. Uruchom bramki jakosci:
   - `php scripts/unit-test-services.php`
   - `php scripts/unit-test-local-module-api.php`
   - Dla KPI summary: `node scripts/integration-test-kpi-summary.mjs`
5. Udokumentuj wynik (sekcja "Dziennik nadzoru" ponizej).
6. Dopiero po review i PASS: `git push origin main`.

## Dziennik nadzoru

### 2026-03-21

- Runner aktywny i stabilny (cykle walidacyjne PASS).
- Zadanie z kolejki `autodev-work-queue.md` wykonane przez agenta.
- Artefakt kodu wygenerowany: `scripts/integration-test-kpi-summary.mjs`.
- Walidacja artefaktu: `kpi-summary-test: PASS`.
- Commit agenta zatwierdzony do dalszego nadzoru: `69bbde8`.

## Zasady push

- Push tylko po zielonych testach i review diffa.
- Nie pushowac plikow runtime (`peartree-autodev/memory/context.json`) jako zmiany produktowej.
- Trzymac commity atomowe: jedna zmiana funkcjonalna + ewentualna dokumentacja.
