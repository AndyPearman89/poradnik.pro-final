# poradnik.pro-final

## Local Dev Stack (Docker)

Szybkie uruchomienie lokalnego WordPress + MySQL:

```bash
cp .env.example .env
docker compose up -d
```

Po starcie otworz http://localhost:8080 i wykonaj jednorazowa instalacje WordPress.

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