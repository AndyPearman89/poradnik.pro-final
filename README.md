# poradnik.pro-final

## Local Dev Stack (Docker)

Szybkie uruchomienie lokalnego WordPress + MySQL:

```bash
cp .env.example .env
docker compose up -d
```

Po starcie otworz http://localhost:8080 i wykonaj jednorazowa instalacje WordPress.

Aktywacja lokalnego modulu E2E (REST API + UI):

```bash
docker compose --profile tools run --rm wpcli plugin activate peartree-local-module/peartree-local-module.php --allow-root
docker compose --profile tools run --rm wpcli option update permalink_structure '/%postname%/' --allow-root
docker compose --profile tools run --rm wpcli rewrite flush --hard --allow-root
```

URL modulu UI:

- http://localhost:8080/modul-e2e/

Przykladowe endpointy API modulu:

- GET http://localhost:8080/wp-json/peartree-local/v1/status
- POST http://localhost:8080/wp-json/peartree-local/v1/echo

## Track Load Testing

Uruchom testy obciazeniowe endpointu /wp-json/peartree/v1/track:

```bash
node scripts/runnee.mjs
```

Raporty zapisywane sa do docs/implementation/reports.

## Quick Deployment

Automatyzacja wdrozenia motywu znajduje sie w:

- scripts/deploy-theme.sh
- docs/implementation/deployment-runbook.md

Przyklad (lokalnie):

```bash
bash scripts/deploy-theme.sh --local-target /var/www/html/wp-content/themes --dry-run
```