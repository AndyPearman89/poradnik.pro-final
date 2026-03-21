# Release Runbook

## Cel

Standaryzacja bezpiecznego wydania zmian: preflight -> deploy -> rollback -> post-deploy checks.

## 1. Preflight

- Potwierdz zielony CI (nightly + PR checks).
- Potwierdz brak krytycznych błedow w `runner.log.jsonl`.
- Potwierdz aktualny backup i restore path.
- Potwierdz status bazy i kontenerow (`docker compose ps`).

## 2. Deploy

- Wykonaj deployment motywu skryptem `scripts/deploy-theme.sh`.
- Uzyj trybu dry-run przed realnym wdrozeniem.
- Po deployu wykonaj aktywacje motywu i `rewrite flush`.

## 3. Rollback

- Przygotuj ostatni stabilny backup motywu.
- Przywroc backup do target path.
- Zweryfikuj homepage, `/wp-json/peartree/v1/track` i lead flow.

## 4. Post-deploy checks

- Uruchom smoke: `node scripts/smoke-test-fe.mjs --base <url> --strict-runtime`.
- Uruchom integracje: lead form + KPI dashboard.
- Uruchom unit tests PHP.
- Uruchom load suite i SLO gate.

## 5. Exit criteria

- Wszystkie testy PASS.
- Brak krytycznych errorow runtime.
- Raporty z testow zarchiwizowane jako artefakty.
