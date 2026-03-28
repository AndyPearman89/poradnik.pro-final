# Drain Procedures - poradnik.pro

## Cel

Bezpieczne zatrzymanie systemu, graceful shutdown serwisow, oraz procedury przywracania w przypadku awarii lub planowanych prac konserwacyjnych.

## Zakres

- Graceful shutdown lokalnego stacku Docker
- Drain autodev agent (zatrzymanie petli autonomicznej)
- Procedury rollback po wykryciu bledu
- Health check i smoke tests przed wznowieniem
- Backup i restore bazy danych

---

## Scenariusz 1: Drain lokalnego stacku Docker

### 1.1 Graceful Shutdown

Zatrzymanie kontenerow WordPress i MySQL bez utraty danych:

```bash
# Zatrzymanie wszystkich serwisow z zachowaniem wolumenow
docker compose down

# Sprawdzenie czy kontenery zostaly zatrzymane
docker compose ps
```

**Weryfikacja:**
- Kontenery powinny byc w stanie `Exited` lub nie powinny byc widoczne
- Wolumeny `mysql_data` i `wp_data` powinny pozostac zachowane

### 1.2 Complete Drain z usunieniem wolumenow

UWAGA: Ta operacja usuwa cala baze danych i pliki WordPress!

```bash
# Zatrzymanie i usuniecie wszystkich wolumenow
docker compose down -v

# Weryfikacja usuniecia wolumenow
docker volume ls | grep poradnik
```

### 1.3 Wznowienie po drain

```bash
# Start stacku z istniejacymi wolumenami
docker compose up -d

# Poczekaj na health check MySQL (15-30s)
docker compose logs mysql | grep "ready for connections"

# Idempotentny bootstrap WP
bash scripts/bootstrap-wp.sh --site-url http://localhost:8080 --site-title "Poradnik Pro Local"

# Weryfikacja health check
curl -f http://localhost:8080 || echo "WordPress not ready"
```

---

## Scenariusz 2: Drain Autodev Agent (Fully Autonomous)

### 2.1 Zatrzymanie petli autonomicznej

Dla agenta uruchomionego w kontenerze Docker:

```bash
# Sprawdz status kontenera
docker compose -f peartree-autodev/docker-compose.yml ps

# Graceful stop agenta (pozwala zakonczyc aktualny cykl)
docker compose -f peartree-autodev/docker-compose.yml stop

# Weryfikacja zatrzymania
docker compose -f peartree-autodev/docker-compose.yml ps
```

Dla agenta uruchomionego lokalnie:

```bash
# Znajdz proces
pgrep -af "python.*runner.py"

# Wyslij SIGTERM dla graceful shutdown
pkill -TERM -f "python.*runner.py"

# Poczekaj 10s i zweryfikuj zakonczenie
sleep 10
pgrep -af "python.*runner.py" || echo "Agent stopped"
```

### 2.2 Weryfikacja stanu przed drain

Przed zatrzymaniem agenta sprawdz:

```bash
# Ostatnie cykle w logach
tail -n 10 peartree-autodev/logs/runner.log.jsonl

# Status git (uncommitted changes?)
git status

# Ostatni commit agenta
git log --oneline -1
```

### 2.3 Wznowienie agenta po drain

```bash
# Start kontenera Docker
docker compose -f peartree-autodev/docker-compose.yml up -d --build

# Monitoring logow w czasie rzeczywistym
docker compose -f peartree-autodev/docker-compose.yml logs -f

# Weryfikacja cyklu pracy (powinien wykonac plan -> execute -> review)
tail -f peartree-autodev/logs/runner.log.jsonl
```

---

## Scenariusz 3: Rollback po wykryciu bledu

### 3.1 Rollback motywu WordPress

Jesli deploy wprowadzil bledy w motywie:

```bash
# Sprawdz dostepne backupy
ls -lth /var/www/html/wp-content/themes/ | grep backup

# Przywroc ostatni backup
cd /var/www/html/wp-content/themes/
mv poradnik.pro poradnik.pro-failed
mv poradnik.pro.backup-YYYYMMDD-HHMMSS poradnik.pro

# Weryfikacja
curl -f http://localhost:8080 || echo "Theme rollback needed"
```

### 3.2 Rollback commita agenta

Jesli agent stworzyl commit z blednym kodem:

```bash
# Sprawdz ostatnie commity
git log --oneline -5

# Soft reset (zachowuje zmiany w working directory)
git reset --soft HEAD~1

# Hard reset (UWAGA: usuwa wszystkie zmiany!)
git reset --hard HEAD~1

# Weryfikacja rollback
git log --oneline -1
```

### 3.3 Rollback bazy danych

Przywracanie z backupu MySQL:

```bash
# Backup bazy przed zmianami
docker compose exec mysql mysqldump -u wordpress -pwordpress wordpress > backup-$(date +%Y%m%d-%H%M%S).sql

# Restore z backupu
docker compose exec -T mysql mysql -u wordpress -pwordpress wordpress < backup-YYYYMMDD-HHMMSS.sql

# Weryfikacja restore
docker compose exec mysql mysql -u wordpress -pwordpress -e "SELECT COUNT(*) FROM wp_posts;" wordpress
```

---

## Scenariusz 4: Emergency Drain (Incydent produkcyjny)

### 4.1 Natychmiastowe zatrzymanie wszystkich serwisow

```bash
# Stop wszystkich kontenerow
docker compose down
docker compose -f peartree-autodev/docker-compose.yml down

# Kill wszystkich procesow agenta (jesli dziala lokalnie)
pkill -9 -f "python.*runner.py"

# Weryfikacja
docker ps -a
pgrep -af python
```

### 4.2 Snapshot aktualnego stanu

```bash
# Git status i diff
git status > emergency-state-$(date +%Y%m%d-%H%M%S).txt
git diff >> emergency-state-$(date +%Y%m%d-%H%M%S).txt

# Backup logow agenta
cp -r peartree-autodev/logs peartree-autodev/logs-emergency-$(date +%Y%m%d-%H%M%S)

# Docker logs
docker compose logs > docker-logs-$(date +%Y%m%d-%H%M%S).txt
```

### 4.3 Post-incident analysis

Po rozwiazaniu incydentu:

1. Przejrzyj logi agenta: `peartree-autodev/logs/runner.log.jsonl`
2. Sprawdz commity agenta: `git log --author="autodev" --oneline -20`
3. Uruchom pelny zestaw testow:
   ```bash
   php scripts/unit-test-services.php
   php scripts/unit-test-local-module-api.php
   node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080
   ```
4. Udokumentuj incydent w `docs/implementation/incident-response-checklist.md`

---

## Scenariusz 5: Health Checks przed wznowieniem

### 5.1 Pre-flight checks

Przed wznowieniem systemu sprawdz:

```bash
# Docker health
docker compose ps | grep -i "health"

# MySQL ready
docker compose exec mysql mysqladmin ping -h localhost -u root -prootpassword

# WordPress odpowiada
curl -f http://localhost:8080 || echo "WP not ready"

# REST API dziala
curl -f http://localhost:8080/wp-json/peartree/v1/status || echo "API not ready"
```

### 5.2 Smoke tests

```bash
# FE smoke test
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080 --strict-runtime

# Backend unit tests
php scripts/unit-test-services.php

# Integration tests
node scripts/integration-test-kpi-summary.mjs --base http://127.0.0.1:8080
```

### 5.3 Load test baseline

```bash
# Track endpoint performance
node scripts/runnee.mjs --base http://127.0.0.1:8080

# Check SLO compliance
node scripts/check-track-slo.mjs

# Expected: P95 <= 2000ms, P99 <= 5000ms
```

---

## Scenariusz 6: Planned Maintenance Window

### 6.1 Pre-maintenance checklist

```bash
# 1. Powiadom team o maintenance window
echo "Maintenance scheduled: $(date)"

# 2. Backup bazy danych
docker compose exec mysql mysqldump -u wordpress -pwordpress wordpress > maintenance-backup-$(date +%Y%m%d-%H%M%S).sql

# 3. Backup motywu
tar -czf theme-backup-$(date +%Y%m%d-%H%M%S).tar.gz poradnik.pro/

# 4. Snapshot git state
git log -1 > pre-maintenance-git-state.txt
git status >> pre-maintenance-git-state.txt

# 5. Stop agenta (graceful)
docker compose -f peartree-autodev/docker-compose.yml stop
```

### 6.2 Maintenance operations

Wykonaj planowane prace (upgrade, migration, refactoring)

### 6.3 Post-maintenance verification

```bash
# 1. Start stacku
docker compose up -d
docker compose -f peartree-autodev/docker-compose.yml up -d

# 2. Health checks
bash scripts/bootstrap-wp.sh --site-url http://localhost:8080 --site-title "Poradnik Pro Local"

# 3. Full test suite
php scripts/unit-test-services.php
php scripts/unit-test-local-module-api.php
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080
node scripts/integration-test-kpi-summary.mjs --base http://127.0.0.1:8080

# 4. Performance baseline
node scripts/runnee.mjs --base http://127.0.0.1:8080

# 5. Monitor pierwsze cykle agenta
tail -f peartree-autodev/logs/runner.log.jsonl
```

---

## Monitoring i Alerting

### Metryki do monitorowania

1. **Agent Health:**
   - Liczba cykli wykonanych
   - Liczba sukcesow vs retry
   - Czas ostatniego cyklu
   - Status validation gate

2. **System Health:**
   - MySQL uptime
   - WordPress response time
   - Track endpoint P95/P99
   - Error rate w logach

3. **Git State:**
   - Liczba uncommitted changes
   - Ostatni commit timestamp
   - Liczba commitow agenta vs human

### Alerty

Ustaw alerty dla:

- Agent down > 5 minut
- Validation gate fail > 3 razy z rzedu
- P99 latency > 5000ms
- Error rate > 5% w ostatnich 100 requestow
- MySQL connection failures

---

## Kontakt i Eskalacja

W przypadku problemow:

1. Sprawdz runbook: `docs/implementation/incident-response-checklist.md`
2. Przejrzyj logi: `peartree-autodev/logs/runner.log.jsonl`
3. Wykonaj drain i rollback zgodnie z procedurami powyzej
4. Udokumentuj incydent w `docs/implementation/autodev-supervision-runbook.md`

---

## Podsumowanie

Ten runbook zapewnia bezpieczne procedury:
- Graceful shutdown wszystkich komponentow
- Rollback w przypadku bledow
- Health checks przed wznowieniem
- Emergency drain dla incydentow
- Planned maintenance workflow

Wszystkie operacje drain sa idempotentne i mozna je wykonywac wielokrotnie bez ryzyka utraty danych (jesli nie uzywa sie flagi `-v` lub `--hard`).
