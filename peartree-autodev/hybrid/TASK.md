TASK-C05 zrealizowac monitoring SLA partnerow + alerting.

## Szczegoly zadania

Zaimplementowac system monitoringu SLA (Service Level Agreement) dla partnerow przyjmujacych leady oraz alerting w przypadku naruszen SLA.

## Wymagania

1. **Monitoring SLA partnerow:**
   - Sledzenie czasu odpowiedzi partnera na lead
   - Sledzenie success rate (accepted vs rejected leads)
   - Sledzenie uptime API partnera
   - Przechowywanie metryk w bazie danych

2. **Definicje SLA:**
   - Response time SLA: < 5 sekund
   - Success rate SLA: > 80%
   - API uptime SLA: > 99%

3. **Alerting:**
   - Alert gdy response time > 5s przez 5 kolejnych leadow
   - Alert gdy success rate < 80% w ostatnich 100 leadach
   - Alert gdy API uptime < 99% w ostatniej godzinie
   - Logi alertow w formacie JSON

4. **Implementacja:**
   - Nowa klasa `SlaMonitor` w `poradnik.pro/includes/`
   - Metody: `track_response_time()`, `track_success_rate()`, `check_sla_violations()`
   - Integration w `LeadRouter` service
   - Unit testy dla SLA calculations
   - Integration test dla alerting logic

5. **Storage:**
   - Tabela `wp_partner_sla_metrics` z kolumnami:
     - partner_id
     - metric_type (response_time, success_rate, uptime)
     - metric_value
     - timestamp
   - Retention: 90 dni

## Pliki do modyfikacji/utworzenia

- `poradnik.pro/includes/class-sla-monitor.php` (nowy)
- `poradnik.pro/includes/class-lead-router.php` (update)
- `scripts/unit-test-sla-monitor.php` (nowy)
- `scripts/integration-test-sla-alerting.mjs` (nowy)

## Acceptance Criteria

- ✅ SlaMonitor class utworzona z pelna funkcjonalnoscia
- ✅ Integracja z LeadRouter dziala
- ✅ Unit testy dla SLA calculations PASS
- ✅ Integration test dla alerting logic PASS
- ✅ Dokumentacja w komentarzach PHP
