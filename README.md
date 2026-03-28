# poradnik.pro-final

## Quick Links

- 📚 [Deployment Automation Guide](docs/implementation/deployment-automation.md) - Comprehensive deployment procedures
- 🔄 [Drain Procedures](docs/implementation/drain-procedures.md) - Graceful shutdown and rollback
- 🤖 [Autodev Supervision Runbook](docs/implementation/autodev-supervision-runbook.md) - Agent monitoring and control
- 🚀 [Release Runbook](docs/implementation/release-runbook.md) - Release procedures
- 📊 [Production Metrics Targets](docs/implementation/production-metrics-targets.md) - SLO and performance targets

## Local Dev Stack (Docker)

Szybkie uruchomienie lokalnego WordPress + MySQL:

```bash
cp .env.example .env
docker compose up -d
bash scripts/bootstrap-wp.sh --site-url http://localhost:8080 --site-title "Poradnik Pro Local"
```

Bootstrap jest idempotentny: mozna uruchamiac wielokrotnie bez ryzyka duplikacji instalacji.

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

## PEARTREE AUTODEV SYSTEM v1.0

Autonomiczny agent developerski 24/7 znajduje sie w katalogu:

- peartree-autodev/

### Tryb 1: Fully Autonomous (Auto-commit + Auto-push)

**UWAGA:** Ten tryb automatycznie commituje i pushuje zmiany po przejsciu validation gate!

**Config:** `peartree-autodev/config/agent.yaml`
```json
{
  "mode": "fully_autonomous",
  "push": true
}
```

**Start agenta:**
```bash
# W kontenerze Docker (24/7 loop)
docker compose -f peartree-autodev/docker-compose.yml up -d --build

# Monitoring logow
docker compose -f peartree-autodev/docker-compose.yml logs -f

# Sprawdz cykle w JSONL
tail -f peartree-autodev/logs/runner.log.jsonl
```

### Tryb 2: Senior Dev Controlled (Wymaga manual review)

**Config:** `peartree-autodev/config/agent.yaml`
```json
{
  "mode": "senior_dev_controlled",
  "push": false
}
```

Agent commituje lokalnie, ale nie pushuje bez human review.

**Workflow:**
1. Agent wykonuje cycle i commituje lokalnie
2. Human sprawdza: `git log -1 --stat`
3. Human uruchamia testy: `php scripts/unit-test-services.php`
4. Human pushuje: `git push origin main`

Szczegoly: `docs/implementation/autodev-supervision-runbook.md`

### Uruchomienie lokalne (single cycle test):

```bash
cd peartree-autodev
python agent/runner.py
```

## PEARTREE AUTODEV HYBRID (Python + Copilot) v1.0

Pol-autonomiczny tryb pracy (task -> implementacja w Copilot -> commit/push):

```bash
cd peartree-autodev/hybrid
python runner.py
```

Szczegoly i instrukcja promptu Copilot:

- peartree-autodev/hybrid/README.md