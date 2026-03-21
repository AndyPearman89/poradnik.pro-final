# Final Handover Runbook

## Cel

Przekazanie systemu `poradnik.pro-final` do stabilnej eksploatacji z jasnym podzialem odpowiedzialnosci operacyjnej, kontrola jakosci i gotowoscia rollback.

## Zakres przekazania

- Kod motywu WordPress i automatyzacje CI/CD.
- Runbooki: deploy/release/incydenty/nadzor autodev.
- Testy: smoke, integracyjne, unit i load.
- Monitoring KPI/track/lead oraz dashboard admin.

## Artefakty referencyjne

- `docs/implementation/release-runbook.md`
- `docs/implementation/deployment-runbook.md`
- `docs/implementation/incident-response-checklist.md`
- `docs/implementation/autodev-supervision-runbook.md`
- `docs/implementation/progress-dashboard.md`

## Minimalny zestaw komend operacyjnych

```bash
# 1) Start lokalnego stacku
cp .env.example .env
docker compose up -d

# 2) Idempotentny bootstrap WP
bash scripts/bootstrap-wp.sh --site-url http://localhost:8080 --site-title "Poradnik Pro Local"

# 3) Bramka FE/runtime
node scripts/smoke-test-fe.mjs --base http://127.0.0.1:8080 --strict-runtime

# 4) Bramka quality (backend)
php scripts/unit-test-services.php
php scripts/unit-test-local-module-api.php

# 5) Bramka integracyjna FE
node scripts/integration-test-js-no-jquery.mjs --base http://127.0.0.1:8080
node scripts/integration-test-visual-smoke-home.mjs --base http://127.0.0.1:8080
node scripts/integration-test-a11y-forms-nav.mjs --base http://127.0.0.1:8080

# 6) Bramka wydajnosci + SLO
node scripts/runnee.mjs --base http://127.0.0.1:8080
node scripts/check-track-slo.mjs
```

## Kryteria gotowosci operacyjnej

- Wszystkie bramki quality PASS.
- Brak runtime errors w homepage i endpointach krytycznych.
- Raport load test wygenerowany i archiwizowany.
- Aktywny plan rollback z ostatnim backupem motywu.

## Odpowiedzialnosci po przekazaniu

- Owner produktu: akceptacja release/freeze i decyzje priorytetowe.
- Owner techniczny: review zmian, decyzja push/tag, kontrola runbookow.
- Operator: wykonywanie procedur deploy/rollback/incydent.

## Znane blokady i ograniczenia

- Branch protection (TASK-G04) wymaga recznej konfiguracji w ustawieniach GitHub i nie moze byc domkniety samym kodem repo.
- 7-dniowy warunek zielonego pipeline (TASK-H02) wymaga uplywu czasu i monitoringu nocnych przebiegow.
- Target metryk produkcyjnych (TASK-H03) wymaga danych z realnego ruchu.

## Plan na final release

1. Domknac reczna konfiguracje G04.
2. Utrzymac 7 kolejnych dni zielonych pipeline.
3. Zweryfikowac targety H03 na danych produkcyjnych.
4. Wykonac finalny tag release i freeze zmian krytycznych.
