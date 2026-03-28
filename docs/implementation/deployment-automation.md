# Deployment Automation - poradnik.pro

## Cel

Zautomatyzowane wdrozenie systemu poradnik.pro z pelnym wsparciem dla CI/CD, rollback, health checks i monitoring wydajnosci.

## Zakres

- Automatyczny deploy motywu WordPress
- CI/CD pipeline z GitHub Actions
- Fully autonomous agent deployment
- Health checks i smoke tests
- Rollback procedures
- Performance monitoring i SLO enforcement

---

## Quick Start: Pelne wdrozenie lokalne

### Krok 1: Przygotowanie srodowiska

```bash
# Clone repozytorium
git clone https://github.com/AndyPearman89/poradnik.pro-final.git
cd poradnik.pro-final

# Utworz plik .env z domyslnymi wartosciami
cp .env.example .env

# Opcjonalnie: Dostosuj wartosci w .env
# vim .env
```

### Krok 2: Start lokalnego stacku

```bash
# Start MySQL + WordPress containers
docker compose up -d

# Poczekaj na health check MySQL (15-30s)
docker compose logs mysql | grep "ready for connections"

# Idempotentny bootstrap WordPress
bash scripts/bootstrap-wp.sh \
  --site-url http://localhost:8080 \
  --site-title "Poradnik Pro Local"
```

### Krok 3: Aktywacja modulu E2E

```bash
# Aktywuj plugin peartree-local-module
docker compose --profile tools run --rm wpcli \
  plugin activate peartree-local-module/peartree-local-module.php \
  --allow-root

# Ustaw permalinki
docker compose --profile tools run --rm wpcli \
  option update permalink_structure '/%postname%/' \
  --allow-root

# Flush rewrite rules
docker compose --profile tools run --rm wpcli \
  rewrite flush --hard \
  --allow-root
```

### Krok 4: Weryfikacja deploymentu

```bash
# Health check WordPress
curl -f http://localhost:8080 || echo "WordPress not ready"

# Health check REST API
curl -f http://localhost:8080/wp-json/peartree/v1/status || echo "API not ready"

# Modul E2E UI
curl -f http://localhost:8080/modul-e2e/ || echo "Module not ready"
```

### Krok 5: Start Fully Autonomous Agent

```bash
# Start agenta w kontenerze Docker (24/7 loop)
docker compose -f peartree-autodev/docker-compose.yml up -d --build

# Monitor logow agenta
docker compose -f peartree-autodev/docker-compose.yml logs -f

# Sprawdz cykle w logach JSON
tail -f peartree-autodev/logs/runner.log.jsonl
```

---

## CI/CD Pipeline: GitHub Actions

### Pipeline 1: Nightly Quality (`nightly-quality.yml`)

**Schedule:** Codziennie o 02:15 UTC

**Kroki:**
1. Setup Node.js 20
2. Start Docker stack (MySQL + WordPress)
3. Bootstrap WordPress (idempotent)
4. Seed KPI data
5. **FE Smoke Test** - runtime validation
6. **Lead Form Integration Test** - form submission
7. **JS no-jQuery Test** - regression check
8. **Visual Smoke Test** - homepage rendering
9. **A11y Audit** - accessibility forms/nav
10. **KPI Summary Test** - dashboard E2E
11. **KPI Dashboard E2E Test** - full integration
12. **PHP Unit Tests** - services + local module API
13. **Load Test** - track endpoint performance
14. **SLO Enforcement** - P95 <= 2000ms, P99 <= 5000ms
15. Upload reports
16. Shutdown stack

**Trigger:**
```yaml
on:
  schedule:
    - cron: '15 2 * * *'  # Daily at 02:15 UTC
  workflow_dispatch:      # Manual trigger
```

**Success Criteria:**
- All tests PASS
- P95 latency <= 2000ms
- P99 latency <= 5000ms
- No runtime errors in FE smoke test
- No accessibility violations in A11y audit

### Pipeline 2: Track Load Test (`track-load-test.yml`)

**Trigger:** PR changes to `poradnik.pro/**`, `scripts/**`, `docker-compose.yml`

**Kroki:**
1. Setup Node.js 20
2. Start Docker stack
3. Bootstrap WordPress
4. Run load test suite
5. Enforce SLO gates (P95/P99)
6. Upload performance reports

**PR Check:**
- Status check: `track-load-test` must PASS
- Blocks merge if SLO violated
- Reports uploaded to `docs/implementation/reports/`

---

## Deployment Scenarios

### Scenariusz 1: Lokalny deploy motywu

Deploy do lokalnego WordPress (np. XAMPP, MAMP, Local by Flywheel):

```bash
bash scripts/deploy-theme.sh \
  --local-target /var/www/html/wp-content/themes \
  --backup \
  --source poradnik.pro \
  --theme-slug poradnik.pro
```

**Parametry:**
- `--local-target` - sciezka do katalogu themes na lokalnym WP
- `--backup` - tworzy backup z timestampem przed deploy
- `--source` - katalog zrodlowy motywu (domyslnie: poradnik.pro)
- `--theme-slug` - nazwa motywu w katalogu docelowym

**Dry-run (bez zmian):**
```bash
bash scripts/deploy-theme.sh \
  --local-target /var/www/html/wp-content/themes \
  --dry-run
```

### Scenariusz 2: Zdalny deploy przez SSH

Deploy na serwer produkcyjny przez SSH:

```bash
bash scripts/deploy-theme.sh \
  --ssh deploy@example.com \
  --remote-path /var/www/html/wp-content/themes \
  --backup \
  --theme-slug poradnik.pro
```

**Parametry:**
- `--ssh` - user@host dla polaczenia SSH
- `--remote-path` - sciezka do katalogu themes na zdalnym serwerze
- `--backup` - tworzy backup przed deploy
- `--dry-run` - pokazuje plan rsync bez deploy

**Pre-requisites:**
- SSH key authentication skonfigurowane
- rsync zainstalowany na lokalnej maszynie i zdalnym serwerze
- Uprawnienia zapisu do katalogu themes

### Scenariusz 3: Docker container update

Update motywu w kontenerze Docker bez rebuild:

```bash
# Skopiuj zmiany do running container
docker compose cp poradnik.pro/. wordpress:/var/www/html/wp-content/themes/poradnik.pro/

# Weryfikacja zmian
docker compose exec wordpress ls -lh /var/www/html/wp-content/themes/poradnik.pro/
```

**Uwaga:** Zmiany zostana utracone po restart kontenera. Dla trwalych zmian zbuduj nowy image.

### Scenariusz 4: Automated deploy z GitHub Actions

Trigger deploy przez workflow dispatch:

```bash
# Manual trigger nightly-quality workflow
gh workflow run nightly-quality.yml

# Manual trigger track-load-test workflow
gh workflow run track-load-test.yml
```

Lub:
- Push do `main` branch (jesli skonfigurowano CI/CD trigger)
- Otworz PR z zmianami (automatycznie uruchomi track-load-test)

---

## Fully Autonomous Agent Deployment

### Tryb 1: Senior Dev Controlled (Default)

**Config:** `peartree-autodev/config/agent.yaml`

```json
{
  "mode": "senior_dev_controlled",
  "push": false
}
```

Agent commituje lokalnie, ale nie pushuje bez review.

**Workflow:**
1. Agent wykrywa TODO/FIXME w kodzie
2. Generuje rozwiazanie przez codegen
3. Commituje lokalnie
4. Human controller robi review
5. Manual push po aprobacie

### Tryb 2: Fully Autonomous (High Risk!)

**Config:** `peartree-autodev/config/agent.yaml`

```json
{
  "mode": "fully_autonomous",
  "push": true
}
```

Agent commituje I pushuje automatycznie po przejsciu validation gate.

**UWAGA:** Ten tryb wymaga:
- 100% pokrycia testami
- Robustne validation gate
- Monitoring logow 24/7
- Rollback plan na awarie

**Start agenta:**
```bash
# W kontenerze Docker (24/7 loop)
docker compose -f peartree-autodev/docker-compose.yml up -d --build

# Lokalnie (single cycle test)
cd peartree-autodev
python agent/runner.py
```

**Monitoring:**
```bash
# Logi w czasie rzeczywistym
docker compose -f peartree-autodev/docker-compose.yml logs -f

# JSONL logs (structured)
tail -f peartree-autodev/logs/runner.log.jsonl

# Sprawdz ostatnie commity agenta
git log --author="autodev" --oneline -10
```

### Tryb 3: Hybrid (Python + Copilot Chat)

**Lokalizacja:** `peartree-autodev/hybrid/`

**Workflow:**
1. Python zapisuje task do `TASK.md`
2. Developer otwiera TASK.md w VS Code
3. GitHub Copilot Chat implementuje task
4. Developer wciska ENTER
5. Python commituje i pushuje

**Start:**
```bash
cd peartree-autodev/hybrid
python runner.py
```

Szczegoly: `peartree-autodev/hybrid/README.md`

---

## Validation Gates i Quality Checks

### Pre-deploy Validation

Przed kazda zmiana kodu uruchom:

```bash
# PHP lint
find poradnik.pro -name "*.php" -exec php -l {} \;

# Unit tests
php scripts/unit-test-services.php
php scripts/unit-test-local-module-api.php

# FE smoke test
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080 --strict-runtime

# Integration tests
node scripts/integration-test-kpi-summary.mjs --base http://127.0.0.1:8080
node scripts/integration-test-visual-smoke-home.mjs --base http://127.0.0.1:8080
```

### Post-deploy Validation

Po deploy sprawdz:

```bash
# Health check WordPress
curl -f http://localhost:8080 || echo "FAILED"

# Health check REST API
curl -f http://localhost:8080/wp-json/peartree/v1/status || echo "FAILED"

# Load test (SLO enforcement)
node scripts/runnee.mjs --base http://127.0.0.1:8080
node scripts/check-track-slo.mjs

# Expected: P95 <= 2000ms, P99 <= 5000ms
```

### Continuous Monitoring

Setup monitoring dla:

1. **Latency Metrics:**
   - P50, P95, P99 dla /track endpoint
   - Target: P95 <= 2000ms, P99 <= 5000ms

2. **Error Rate:**
   - 4xx/5xx errors w ostatnich 100 requestow
   - Target: < 5% error rate

3. **Availability:**
   - Uptime WordPress
   - Uptime MySQL
   - Agent health (last cycle timestamp)

4. **Agent Metrics:**
   - Cycles executed
   - Success vs retry count
   - Validation gate failures
   - Last push timestamp

---

## Rollback Procedures

### Rollback 1: Motyw WordPress

```bash
# Sprawdz dostepne backupy
ls -lth /var/www/html/wp-content/themes/ | grep backup

# Przywroc ostatni backup
cd /var/www/html/wp-content/themes/
mv poradnik.pro poradnik.pro-failed
mv poradnik.pro.backup-20260328-123456 poradnik.pro

# Weryfikacja
curl -f http://localhost:8080
```

### Rollback 2: Git commit

```bash
# Soft reset (zachowuje zmiany)
git reset --soft HEAD~1

# Hard reset (usuwa zmiany!)
git reset --hard HEAD~1

# Weryfikacja
git log --oneline -5
```

### Rollback 3: Docker container

```bash
# Przywroc poprzedni image
docker compose down
docker compose pull  # Pull last stable image
docker compose up -d

# Weryfikacja
docker compose ps
```

### Rollback 4: Baza danych

```bash
# Restore z backupu
docker compose exec -T mysql mysql -u wordpress -pwordpress wordpress < backup-20260328-123456.sql

# Weryfikacja
docker compose exec mysql mysql -u wordpress -pwordpress -e "SELECT COUNT(*) FROM wp_posts;" wordpress
```

---

## Performance Tuning

### Opcja 1: PHP OpCache

Enable OpCache w `docker-compose.yml`:

```yaml
services:
  wordpress:
    environment:
      - PHP_OPCACHE_ENABLE=1
      - PHP_OPCACHE_MEMORY_CONSUMPTION=128
```

### Opcja 2: MySQL Query Cache

Tune MySQL w `docker-compose.yml`:

```yaml
services:
  mysql:
    command: --query-cache-size=64M --query-cache-type=1
```

### Opcja 3: Redis Object Cache

Add Redis service:

```yaml
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
```

Install WordPress Redis plugin:

```bash
docker compose --profile tools run --rm wpcli plugin install redis-cache --activate --allow-root
```

---

## Security Considerations

### Secrets Management

**NIGDY nie commituj:**
- `.env` file
- Database passwords
- API keys
- SSH private keys

**Use:**
- `.env.example` jako template
- GitHub Secrets dla CI/CD
- Environment variables w Docker

### Rate Limiting

Track endpoint ma rate limiting:

```php
// poradnik.pro/includes/class-analytics-service.php
private const RATE_LIMIT_MAX = 100;
private const RATE_LIMIT_WINDOW = 3600;
```

Dostosuj wedlug potrzeb produkcyjnych.

### HTTPS/SSL

Dla produkcji skonfiguruj:

1. SSL certificate (Let's Encrypt)
2. Force HTTPS redirect
3. HSTS headers
4. Secure cookies

---

## Troubleshooting

### Problem 1: WordPress nie startuje

```bash
# Sprawdz logi MySQL
docker compose logs mysql | tail -50

# Sprawdz logi WordPress
docker compose logs wordpress | tail -50

# Sprawdz health check
docker compose ps
```

### Problem 2: Agent nie wykonuje cykli

```bash
# Sprawdz logi agenta
docker compose -f peartree-autodev/docker-compose.yml logs

# Sprawdz config
cat peartree-autodev/config/agent.yaml

# Sprawdz proces
docker compose -f peartree-autodev/docker-compose.yml ps
```

### Problem 3: Tests failing

```bash
# Run verbose tests
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080 --verbose

# Check PHP errors
docker compose exec wordpress tail -f /var/www/html/wp-content/debug.log

# Check MySQL connection
docker compose exec mysql mysqladmin ping -h localhost -u root -prootpassword
```

### Problem 4: High latency

```bash
# Run load test with profiling
node scripts/runnee.mjs --base http://127.0.0.1:8080 --profile

# Check slow queries
docker compose exec mysql mysql -u root -prootpassword -e "SHOW FULL PROCESSLIST;"

# Enable WordPress debug
# Set WORDPRESS_DEBUG=1 in .env
```

---

## Production Deployment Checklist

Pre-deployment:
- [ ] All tests PASS (unit, integration, load)
- [ ] SLO gates PASS (P95 <= 2000ms, P99 <= 5000ms)
- [ ] Backup bazy danych utworzony
- [ ] Backup motywu utworzony
- [ ] Rollback plan udokumentowany
- [ ] Health checks skonfigurowane
- [ ] Monitoring w miejscu
- [ ] Secrets zabezpieczone
- [ ] SSL/HTTPS skonfigurowane
- [ ] Rate limiting skonfigurowane

Post-deployment:
- [ ] Health checks PASS
- [ ] Smoke tests PASS
- [ ] Performance baseline sprawdzony
- [ ] Error rate < 5%
- [ ] Monitoring dziala
- [ ] Logs sa archiwizowane
- [ ] Incident response plan gotowy

---

## References

- [Deployment Runbook](./deployment-runbook.md)
- [Drain Procedures](./drain-procedures.md)
- [Autodev Supervision Runbook](./autodev-supervision-runbook.md)
- [Release Runbook](./release-runbook.md)
- [Incident Response Checklist](./incident-response-checklist.md)
- [Production Metrics Targets](./production-metrics-targets.md)

---

## Podsumowanie

Ten dokument zapewnia:
- ✅ Powtarzalne procedury deploymentu
- ✅ CI/CD automation z GitHub Actions
- ✅ Fully autonomous agent deployment
- ✅ Comprehensive validation gates
- ✅ Rollback procedures dla kazdego komponentu
- ✅ Performance monitoring i SLO enforcement
- ✅ Security best practices
- ✅ Troubleshooting guide

Wszystkie procedury sa idempotentne i mozna je wykonywac wielokrotnie bez ryzyka.
