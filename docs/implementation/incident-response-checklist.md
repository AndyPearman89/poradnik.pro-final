# Incident Response Checklist

## Scope

Checklist dla awarii krytycznych:

- `/wp-json/peartree/v1/track`
- lead submit flow (`LeadService` / endpoint API)

## Severity klasyfikacja

- SEV-1: calkowity brak trackingu albo lead submit (produkcja)
- SEV-2: czesciowa degradacja, wysoki error rate
- SEV-3: incydent ograniczony, brak istotnego impactu biznesowego

## 0-15 min (Triage)

- Potwierdz symptom i czas startu incydentu.
- Zweryfikuj dashboard KPI i logi aplikacyjne.
- Sprawdz ostatni deploy/rollback i zmiany konfiguracji.
- Ocen impact: tracking, lead conversion, revenue.

## 15-30 min (Containment)

- Dla `/track`: tymczasowo ogranicz źrodla ruchu powodujace bledy.
- Dla lead submit: aktywuj fallback routing lub formularz awaryjny.
- Potwierdz healthcheck endpointow i status HTTP.

## 30-60 min (Mitigation)

- Uruchom smoke test FE i integracje lead/KPI.
- Zweryfikuj unit tests kluczowych serwisow.
- W razie potrzeby wykonaj rollback zgodnie z release runbook.

## Recovery validation

- `node scripts/smoke-test-fe.mjs --base <url>`
- `node scripts/integration-test-lead-form.mjs --base <url>`
- `node scripts/integration-test-kpi-dashboard.mjs --base <url> --admin-user <user> --admin-password <pass>`
- `php scripts/unit-test-services.php`
- `php scripts/unit-test-local-module-api.php`

## Post-incident (do 24h)

- Root cause analysis i timeline incydentu.
- Action items z ownerem i terminem.
- Aktualizacja runbookow i testow regresyjnych.
