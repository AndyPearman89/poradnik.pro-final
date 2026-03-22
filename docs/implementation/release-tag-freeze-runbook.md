# Release Tag & Change Freeze Runbook (TASK-H05)

## Cel

Standaryzacja procesu tworzenia finalnego tagu release oraz zamrazania zmian krytycznych,
zapewniajaca ze kazde wdrozenie produkcyjne ma jednoznaczny identyfikator wersji i udokumentowany stan.

---

## Kiedy uzywac

1. **Pre-production release**: Przed kazdym wdrozeniem na produkcje.
2. **Final freeze**: Po osiagnieciu wszystkich celow (TASK-H01 do TASK-H04).
3. **Post-hotfix**: Po naprawie krytycznego bledu w produkcji.

---

## Skrypt

Sciezka: `scripts/create-release-tag.sh`

```bash
# Podstawowe uzycie (auto-increment wersji, bez freeze):
bash scripts/create-release-tag.sh

# Z explicita wersja i zamrozeniem:
bash scripts/create-release-tag.sh --version v1.0.0 --freeze

# Dry-run (podglad akcji bez wykonywania):
bash scripts/create-release-tag.sh --version v1.0.1 --dry-run --freeze

# Sprawdz czy repo jest zamrozone:
bash scripts/create-release-tag.sh --check-freeze

# Odblokuj (unfreeze) po zakonczeniu:
bash scripts/create-release-tag.sh --unfreeze
```

### Opcje skryptu

| Opcja             | Opis                                                    |
|------------------|---------------------------------------------------------|
| `--version VER`  | Explicit tag (np. `v1.0.0`). Bez tej opcji: auto-patch. |
| `--dry-run`      | Wypisz akcje bez wykonywania.                           |
| `--freeze`       | Stworz plik `.freeze` po otagowaniu (blokada krytycznych zmian). |
| `--unfreeze`     | Usun plik `.freeze`.                                    |
| `--check-freeze` | Sprawdz status freeze (exit 1 = frozen, exit 0 = OK).   |
| `--notes TEXT`   | Niestandardowy opis release.                            |
| `--skip-checks`  | Pomijaj preflight checks (niezalecane).                 |

---

## Pelny proces release

### Krok 1: Weryfikacja gotowosci

```bash
# 1a. Sprawdz ze working tree jest czysty
git status

# 1b. Sprawdz pipeline streak (wymagane >= 7 zielonych dni):
node scripts/track-pipeline-streak.mjs --check --threshold 7

# 1c. Uruchom pelen zestaw testow lokalnie
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080 --strict-runtime
php scripts/unit-test-services.php
php scripts/unit-test-local-module-api.php

# 1d. Sprawdz metryki produkcyjne:
node scripts/check-production-metrics.mjs
```

### Krok 2: Tworzenie tagu

```bash
# Dry-run najpierw:
bash scripts/create-release-tag.sh --version v1.0.0 --dry-run --freeze

# Jesli wyglada OK – wykonaj rzeczywisty tag z freeze:
bash scripts/create-release-tag.sh --version v1.0.0 --freeze --notes "Final production release"
```

### Krok 3: Weryfikacja tagu

```bash
# Sprawdz tag:
git show v1.0.0

# Sprawdz liste tagow:
git tag --list "v*" --sort=-version:refname | head -5

# Weryfikuj ze repo jest zamrozone:
bash scripts/create-release-tag.sh --check-freeze
```

### Krok 4: Post-deploy checks

Wykonaj kroki z `docs/implementation/release-runbook.md`:

```bash
# Smoke:
node scripts/smoke-test-fe.mjs --base https://poradnik.pro --strict-runtime

# Integration:
node scripts/integration-test-lead-form.mjs --base https://poradnik.pro
node scripts/integration-test-kpi-dashboard.mjs --base https://poradnik.pro

# Load + SLO:
node scripts/runnee.mjs --base https://poradnik.pro
node scripts/check-track-slo.mjs
```

### Krok 5: Unfreeze (po stabilizacji produkcji)

```bash
# Po min. 24h stabilnej produkcji:
bash scripts/create-release-tag.sh --unfreeze
```

---

## Semantyczne wersjonowanie

Projekt stosuje [Semantic Versioning 2.0.0](https://semver.org/):

```
MAJOR.MINOR.PATCH
```

| Typ zmiany                      | Bump     | Przyklad       |
|---------------------------------|----------|----------------|
| Breaking change / redesign      | MAJOR    | v1.0.0 → v2.0.0 |
| Nowa funkcjonalnosc              | MINOR    | v1.0.0 → v1.1.0 |
| Bugfix / patching / hotfix      | PATCH    | v1.0.0 → v1.0.1 |

Skrypt `create-release-tag.sh` bez `--version` auto-incrementuje PATCH.

---

## Plik FREEZE (`.freeze`)

Plik `.freeze` w katalogu glownym sygnalizuje zamrozenie zmian krytycznych.

### Zawartosc:

```
FREEZE
======
Tag:       v1.0.0
Commit:    abc1234
Branch:    main
Frozen at: 2026-03-25T12:00:00Z

Critical changes are LOCKED as of tag v1.0.0.
...
```

### Integracja z CI

Mozna dodac gate do pipeline ktory odrzuca PR z krytycznymi zmianami gdy repo jest zamrozone:

```bash
# W pre-commit hook lub CI step:
bash scripts/create-release-tag.sh --check-freeze
# Exit 1 = frozen = blokuj merge krytycznych zmian
```

### Zakres zamrozenia

Zamrozenie dotyczy:
- `poradnik.pro/` – kod motywu
- `.github/workflows/` – definicje CI
- `scripts/` – skrypty deploy/bootstrap
- Pliki konfiguracyjne: `docker-compose.yml`, `.env.example`

Dozwolone podczas freeze:
- Dokumentacja (`docs/`)
- Raporty testow
- Konfiguracja nieprodukcyjna

---

## Historia tagow

Konwencja nazewnictwa tagu:
- `v{MAJOR}.{MINOR}.{PATCH}` – release produkcyjny
- `v{X}.{Y}.{Z}-rc{N}` – release candidate (np. `v1.0.0-rc1`)
- `v{X}.{Y}.{Z}-hotfix{N}` – hotfix po freeze

```bash
# Wylistuj tagi z datami:
git tag --list "v*" --sort=-version:refname --format='%(refname:short) %(taggerdate:short)'
```

---

## Definicja ukonczenia TASK-H05

TASK-H05 jest DONE gdy:

1. `scripts/create-release-tag.sh` istnieje i dziala (dry-run i real-run).
2. Pierwszy production tag zostal utworzony (np. `v1.0.0`).
3. Plik `.freeze` moze byc tworzony i usuwany przez skrypt.
4. Ten runbook jest zaktualizowany i dostepny.
5. Tasklist: TASK-H05 → DONE.

---

## Referencje

- `docs/implementation/release-runbook.md`
- `docs/implementation/deployment-runbook.md`
- `docs/implementation/pipeline-streak-tracking.md`
- `scripts/track-pipeline-streak.mjs`
- `scripts/check-production-metrics.mjs`
- [Semantic Versioning](https://semver.org/)
