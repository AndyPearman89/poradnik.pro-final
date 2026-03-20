# Deployment Runbook - poradnik.pro

## Cel

Powtarzalne i bezpieczne wdrozenie motywu WordPress z repozytorium do srodowiska lokalnego lub zdalnego.

## Narzedzie

- scripts/deploy-theme.sh

## Scenariusz 0: Lokalny stos Docker (dev)

Wymagania: Docker, Docker Compose v2.

```bash
# 1. Skopiuj plik ze zmiennymi srodowiskowymi
cp .env.example .env

# 2. Uruchom MySQL + WordPress na porcie 8080
docker compose up -d

# 3. Poczekaj ~15s na inicjalizacje WP i wejdz na http://localhost:8080
#    Wykonaj jednorazowe instalacje WP (moze byc przez wp-cli lub UI)

# 4. Sprawdz aktywny temat i uruchom testy /track
node scripts/runnee.mjs
```

Zatrzymanie stosu:

```bash
docker compose down
```

Calkowite usuniecie wolumenow (baza danych):

```bash
docker compose down -v
```

---

## Scenariusz 1: Lokalny WordPress

Przyklad dla lokalnego WP:

```bash
bash scripts/deploy-theme.sh \
  --local-target /var/www/html/wp-content/themes \
  --backup
```

Dry-run (bez zmian na dysku):

```bash
bash scripts/deploy-theme.sh \
  --local-target /var/www/html/wp-content/themes \
  --dry-run
```

## Scenariusz 2: Zdalny serwer (SSH)

Przyklad:

```bash
bash scripts/deploy-theme.sh \
  --ssh deploy@example.com \
  --remote-path /var/www/html/wp-content/themes \
  --backup
```

Dry-run zdalny:

```bash
bash scripts/deploy-theme.sh \
  --ssh deploy@example.com \
  --remote-path /var/www/html/wp-content/themes \
  --dry-run
```

## Parametry

- --source: katalog zrodlowy motywu (domyslnie: poradnik.pro)
- --theme-slug: nazwa katalogu motywu po stronie targetu (domyslnie: poradnik.pro)
- --backup: tworzy kopie zapasowa folderu motywu z timestampem
- --dry-run: pokazuje plan zmian rsync bez wdrozenia

## Weryfikacja po wdrozeniu

1. Wejdz do WP Admin -> Wyglad -> Motywy i sprawdz aktywacje poradnik.pro.
2. Otworz strone glowna i sprawdz sekcje hero + API widgets.
3. Zweryfikuj endpoint track przez konsolowy event z frontendu.
4. Zweryfikuj dashboard KPI w WP Admin.

## Rollback

Jesli wdrozenie pogorszy dzialanie, przywroc ostatni backup katalogu motywu:

- lokalnie: skopiuj folder *.backup-YYYYMMDD-HHMMSS nad aktywny katalog motywu
- zdalnie: przywroc backup przez ssh + mv/cp
