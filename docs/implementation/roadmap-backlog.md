# Roadmap i Backlog Wdrozenia

## Status wdrozenia

- 2026-03-20: Etap 1 (Foundation MVP techniczne) uruchomiony jako kod w katalogu poradnik.pro.
- Zrealizowano: scaffold motywu, tokeny CSS, template parts home, front-page, warstwa API i serwisy (Search/Lead/SEO/Schema/UI/Performance), JS bez jQuery.
- 2026-03-20: Etap 2 (Conversion Core) uruchomiony w wersji bazowej: retry+backoff i walidacja lead form, tracking payload contract, CTA injection logic, trust/urgency components.
- 2026-03-20: Etap 3 (SEO Scale) uruchomiony w wersji bazowej: rozszerzone meta i schema zalezne od intentu, finalizacja template Q&A oraz local archive, automatyczny blok internal linking.
- 2026-03-20: Etap 4 (Monetization Optimization) uruchomiony w wersji bazowej: ranking premium weighting, comparison table, affiliate CTA system z disclosure i fallback lead CTA.
- 2026-03-20: Etap 5 (Growth i AI) uruchomiony w wersji bazowej: intent-aware search mapping, prompt pipeline dla content+FAQ, A/B variant dla sekcji CTA.
- 2026-03-20: Etap 6 (Marketing Distribution) uruchomiony w wersji bazowej: deep-link templates dla YouTube Shorts/Pinterest/Discover oraz tracking attribution landing i channel click.
- 2026-03-20: Hardening produkcyjny uruchomiony w wersji bazowej: dashboard KPI w WP Admin i automatyczne raportowanie eventow attribution -> monetization przez endpoint /track.
- 2026-03-20: Kalibracja estymacji revenue uruchomiona: konfigurowalne stawki affiliate/lead w dashboardzie KPI + podsumowanie 14 dni i top sources.
- 2026-03-20: Retencja danych telemetryki uruchomiona: rolling window (14-365 dni) i eksport CSV z dashboardu KPI.
- 2026-03-20: Dodano skrypt testow obciazeniowych endpointu /track (Node, bez zaleznosci) i dokumentacje uruchomienia.
- 2026-03-20: Dodano suite runner testow /track (baseline + peak) z automatycznym raportem markdown.
- 2026-03-20: Wykonano probe suite runnera; raport wygenerowany, ale test zablokowany przez niedostepny endpoint WP w kontenerze (Endpoint unreachable).
- 2026-03-20: Dodano runnee (autonomiczny entrypoint) z autodetekcja base URL i delegacja do suite runnera.
- 2026-03-20: Dodano automatyzacje deploymentu motywu (local/ssh, dry-run, backup) + runbook wdrozeniowy.
- 2026-03-20: Wykonano realny lokalny deploy testowy motywu do /tmp/wp-content/themes/poradnik.pro (walidacja artefaktow OK).
- 2026-03-20: Wykonano lokalny deploy z backupem (backup utworzony, deployment complete, katalog motywu aktywny w target testowym).
- 2026-03-20: Po deployu uruchomiono runnee; test /track nadal zablokowany przez brak dostepnego lokalnego WordPress URL (exit code 2).
- 2026-03-20: Stworzono docker-compose.yml (MySQL 8.0 + WordPress 6.5-php8.1 na porcie 8080) i .env.example; dodano retry/backoff (3 proby, 300ms backoff) do isReachable() w runnee i probe() w load-test-track; runbook rozszerzony o Scenariusz 0 (local dev stack).
- 2026-03-20: Uruchomiono stos Docker (docker compose up -d), runnee wykryl WP na http://127.0.0.1:8080. Testy /track PASS (overall): Baseline 500 req OK=500 fail=0 RPS=17.22 p95=1128ms p99=3318ms; Peak 2000 req OK=2000 fail=0 RPS=17.43 p95=3226ms p99=4263ms. Raport: docs/implementation/reports/track-load-report-20260320-212259.md.
- 2026-03-20: Dodano GitHub Actions workflow .github/workflows/track-load-test.yml dla automatycznego /track load-test na PR (docker compose + bootstrap WP przez wp-cli + aktywacja motywu + upload raportu).
- 2026-03-20: Dodano bramke SLO w CI dla scenariusza Baseline (p95<=2000ms, p99<=5000ms) przez skrypt scripts/check-track-slo.mjs uruchamiany po suite runnerze.
- 2026-03-20: Dodano smoke test FE scripts/smoke-test-fe.mjs: waliduje stronę główną (status 200, znaczniki HTML), tracking endpoint i integrację między FE a /track endpoint. Uruchamiany w CI po aktywacji motywu i przed load testem.
- 2026-03-20: Dodano bezpieczeństwo endpointu /track: proper permission callback, security headers (Cache-Control, X-Content-Type-Options, X-Frame-Options), rate limiting stub (const RATE_LIMIT_REQUESTS_PER_MIN=300), audit logging w WP_DEBUG mode, sanitizacja Input. Dokumentacja: docs/implementation/security-rate-limiting.md.
- 2026-03-20: Dodano integracyjny test lead form scripts/integration-test-lead-form.mjs (lead_form_displayed, lead_form_submit_attempt, lead_submit_success) i podpięto go w CI po smoke teście FE.
- 2026-03-20: Dodano unit testy serwisów w scripts/unit-test-services.php: AnalyticsService::pruneStore (retention logic) oraz LeadService::submit (sanitizacja payload i honeypot short-circuit). Testy podpięte w CI.
- 2026-03-20: Rozszerzono unit testy serwisów o revenue math w AnalyticsService::ingestEvent oraz edge-case błędu API w LeadService::submit.
- 2026-03-20: Dodano asercje security contract dla endpointu /track w scripts/unit-test-services.php: weryfikacja permission_callback w registerRestRoutes oraz nagłówków bezpieczeństwa (Cache-Control, Pragma, X-Content-Type-Options, X-Frame-Options) w ingestEvent.
- 2026-03-20: Dodano edge-case testy dla AnalyticsService w scripts/unit-test-services.php: retention_days=1 oraz odporność ingestEvent na niepoprawny payload (brak eventName/source).
- 2026-03-20: Dodano test kontraktu CSV export w scripts/unit-test-services.php: nagłówki exportu, kolumny CSV i sortowanie dni rosnąco.
- 2026-03-20: Dodano testy regresyjne config form i export flow w scripts/unit-test-services.php: clamp retention_days do zakresu 14-365 oraz nonce-flow (invalid nonce => early return, bez outputu CSV).
- 2026-03-20: Dodano testy scenariusza export request z poprawnym nonce (payload CSV + Content-Disposition) przez izolowany adapter I/O (buildExportPayloadFromRequest), bez użycia exit w testach.
- 2026-03-20: Dodano regresyjne testy eksportu CSV dla pustego store (tylko header row) oraz smoke-check nazwy pliku z timestampem (Ymd-His).
- 2026-03-20: Dodano testy negatywne export request dla braku uprawnień manage_options oraz braku parametru poradnik_pro_export=csv.
- 2026-03-20: Dodano testy kontraktu wartości eksportu CSV: format revenue do 2 miejsc po przecinku oraz fallback top_source=unknown i top_source_events=0.
- 2026-03-20: Dodano test kontraktu AnalyticsService::buildSummary dla wielodniowego store: agregacja lead_success, affiliate_clicks, estimated_total_revenue oraz top_sources (sumowanie i sortowanie malejące).
- 2026-03-20: Dodano test fallbacku AnalyticsService::buildSummary dla pustego inputu (rows=[]): lead_success=0, affiliate_clicks=0, estimated_total_revenue=0.0, top_sources=[].
- 2026-03-20: Dodano test limitu top_sources w AnalyticsService::buildSummary: przy 12 źródłach zwracane jest dokładnie 10 najwyższych (malejąco, do pozycji 10).
- 2026-03-20: Dodano test stabilności AnalyticsService::buildSummary dla brakujących kluczy revenue/sources w jednym dniu (defensywny fallback bez crasha i poprawna agregacja danych dostępnych).
- 2026-03-20: Dodano test stabilności buildSummary dla niepoprawnych typów w sources (string/null/non-numeric/negative) i wdrozono bezpieczna normalizacje do int >= 0 w AnalyticsService::normalizeSourceCount.
- 2026-03-20: Dodano test buildSummary dla scenariusza >10 sources z remisami count oraz wdrozono deterministyczne sortowanie tie-break po nazwie source (ASC).
- 2026-03-21: Dodano test integracyjny kontraktu /track dla sortowania top_sources po tie na danych z wielu dni (ingestEvent + buildSummary) w scripts/unit-test-services.php.
- 2026-03-21: Wdrozono tryb operacyjny "Senior Dev Agent + Human Controller": push kontrolowany (manualny), runbook nadzorczy i walidacja artefaktu scripts/integration-test-kpi-summary.mjs.
- Kolejny krok: dodać test E2E (HTTP) w stacku Docker, który potwierdzi widoczność uporządkowanych top_sources w dashboardzie KPI dla danych wielodniowych.

## Etap 1: Foundation (MVP techniczne)

Cele:

- uruchomienie theme poradnik.pro i design system
- podstawowe szablony i sekcje home
- wspolna warstwa API + enqueue + performance

Backlog:

- scaffold struktury motywu
- implementacja CSS tokenow
- implementacja template parts
- front-page z dynamicznymi sekcjami
- SearchService i LeadService

Kryteria odbioru:

- strona dziala bez jQuery
- Core Web Vitals mobile na poziomie produkcyjnym
- API integration i fallback errors gotowe

## Etap 2: Conversion Core

Cele:

- finalizacja lead flow
- sticky CTA mobile
- tracking kluczowych zdarzen

Backlog:

- leads.js z retry i honeypot
- tracking.js (CTA/search/lead/scroll)
- urgency i trust components
- CTA injection logic

Kryteria odbioru:

- formularze wysylaja leady do /leads
- event tracking emituje poprawne payloady
- UX mobilny bez tarcia

## Etap 3: SEO Scale

Cele:

- wdrozenie local SEO i q&a templates
- schema i dynamiczne meta

Backlog:

- single-question i archive-local
- SeoService i SchemaService
- internal linking automation

Kryteria odbioru:

- poprawna walidacja structured data
- indeksowalne, szybkie strony lokalne

## Etap 4: Monetization Optimization

Cele:

- ranking premium weighting
- porownania i afiliacja
- uruchomienie modelu hybrydowego ads+afiliacja+lead

Backlog:

- ranking cards z badge logic
- comparison tables
- affiliate CTA system
- placement engine dla slotow ads
- intent routing do modelu monetyzacji

Kryteria odbioru:

- top 3 ranking logic dziala dynamicznie
- monetized placement kontrolowany konfiguracja
- mierzalny revenue mix per page type

## Etap 5: Growth i AI

Cele:

- AI-ready search UX
- content automation i skala

Backlog:

- rozszerzenie search mapping
- prompt pipeline dla tresci i FAQ
- eksperymenty A/B na sekcjach konwersyjnych
- automatyzacja osadzania afiliacji i CTA wg intencji
- testy balansu ads density vs UX

Kryteria odbioru:

- wzrost CR i SEO widoczny w metrykach kwartalnych
- wzrost revenue per user/session przy stabilnym UX mobile

## Etap 6: Marketing Distribution

Cele:

- polaczenie SEO z kanalami wspierajacymi ruch
- domkniecie petli content -> monetization

Backlog:

- szablony deep-link pod YouTube Shorts i Pinterest
- bloki hook pod Discover-friendly artykuly
- tracking attribution dla kanalow ruchu

Kryteria odbioru:

- widoczna dywersyfikacja ruchu
- potwierdzone intent-to-monetization match rate

## Harmonogram 0-60 dni (System 1/2/3)

### Dni 0-14

Cele:

- publikacja 50 poradnikow low intent
- aktywacja slotow AdSense i pomiar RPM/CTR

Kryteria odbioru:

- stabilny ads layout mobile-first
- brak negatywnego wpływu reklam na CTA lead

### Dni 14-30

Cele:

- publikacja 20 rankingow mid intent
- wdrozenie afiliacji (TOP 3, tabele, rekomendacje)

Kryteria odbioru:

- mierzalny EPC i affiliate CR
- poprawny disclosure i zgodnosc UX

### Dni 30-60

Cele:

- publikacja 50 landingow lokalnych high intent
- uruchomienie pelnego flow lead (formularz + routing + exclusive)

Kryteria odbioru:

- CR lead na poziomie produkcyjnym
- SLA odpowiedzi partnerow i wysoka akceptacja leadow

## Plan SEO Enterprise 0-90 dni

### Dni 0-30

Cele:

- 100 poradnikow problem SEO
- 20 stron ranking SEO
- 50 landingow local SEO

Kryteria odbioru:

- stabilna indeksacja i poprawna kanonikalizacja
- poprawny mapping intent do ads/afiliacja/lead

### Dni 30-90

Cele:

- skalowanie do 1000 stron programmatic SEO
- automatyzacja meta/schema/linkowania wewnetrznego

Kryteria odbioru:

- rosnaca liczba keywordow i ruchu organicznego
- utrzymanie jakosci UX i konwersji przy skali

### Dni 90+

Cele:

- automation hardening dla content/meta/schema/linkowania
- skalowanie programmatic SEO ponad 1000 stron

Kryteria odbioru:

- stabilna jakosc tresci przy rosnacej liczbie publikacji
- brak regresji CWV i konwersji

## Ryzyka SEO Enterprise

- thin content przy szybkiej skali
- niedostateczne linkowanie wewnetrzne
- slaba jakość UX na mobile
- brak CTA w kluczowych szablonach

## Plan Complete System 0-90 dni

### Dni 0-30

Cele:

- 100 guide pages
- aktywacja warstwy AdSense

Kryteria odbioru:

- stabilny RPM i CTR na ruchu low intent
- brak degradacji UX i CTA

### Dni 30-60

Cele:

- wdrozenie rankingow i afiliacji
- aktywacja komponentow TOP3/box/tabela

Kryteria odbioru:

- mierzalny EPC i affiliate CR
- poprawny disclosure i zgodnosc z UX

### Dni 60-90

Cele:

- skalowanie local pages i lead engine
- routing multi/exclusive lead

Kryteria odbioru:

- CR lead i wartosc leada na poziomie produkcyjnym
- stabilny flow formularz -> routing -> odpowiedzi

## Taski wykonawcze do konca projektu

Status legend:

- OPEN: task zaplanowany
- WIP: task w realizacji
- BLOCKED: task zablokowany zaleznoscia
- DONE: task zakonczony i zwalidowany

### Faza A - Platforma i niezawodnosc

- TASK-A01 [OPEN] Zrobic bootstrap WP bezbledny w CI i local (idempotent install + activate theme + rewrite flush).
- TASK-A02 [OPEN] Dodac healthcheck endpointu `/wp-json/peartree/v1/track` do smoke suite.
- TASK-A03 [OPEN] Dodac watchdog log rotation dla `peartree-autodev/logs/*.jsonl`.
- TASK-A04 [OPEN] Ujednolicic zmienne srodowiskowe `.env` vs CI secrets i opisac fallback.
- TASK-A05 [OPEN] Dodac twardy gate dla runtime errors w FE smoke (status!=200 => fail pipeline).

### Faza B - Tracking, KPI i jakosc danych

- TASK-B01 [OPEN] Dodac E2E HTTP test dashboardu KPI dla tie-order `top_sources` na danych wielodniowych.
- TASK-B02 [OPEN] Dodac test kontraktu eksportu CSV dla wysokiego wolumenu (co najmniej 365 dni danych).
- TASK-B03 [OPEN] Dodac walidacje schematu payload dla `/track` (allowlist eventow i source).
- TASK-B04 [OPEN] Dodac testy regresji dla retention przy granicach 14 i 365 dni.
- TASK-B05 [OPEN] Dodac metryke blednych eventow do KPI (invalid payload count).

### Faza C - Lead engine i routing

- TASK-C01 [OPEN] Dodac E2E test lead flow: display -> submit -> success -> attribution.
- TASK-C02 [OPEN] Dodac scenariusze retry/backoff dla bledow API lead endpoint.
- TASK-C03 [OPEN] Dodac routing rule set (multi/exclusive) z testami kontraktu.
- TASK-C04 [OPEN] Dodac walidacje antyspam (honeypot + throttle) z asercjami w testach integracyjnych.
- TASK-C05 [OPEN] Dodac monitoring SLA odpowiedzi partnerow i alert przy degradacji.

### Faza D - SEO i programmatic scale

- TASK-D01 [OPEN] Dodac generator szablonow local pages z kontrola canonical/meta/schema.
- TASK-D02 [OPEN] Dodac automatyczny test structured data (schema required fields).
- TASK-D03 [OPEN] Dodac kontroler internal linking depth (minimalna liczba linkow wewnetrznych/strone).
- TASK-D04 [OPEN] Dodac testy renderu dla `single-question`, `archive-local`, `single-ranking`.
- TASK-D05 [OPEN] Dodac bramke "thin-content risk" (minimalna dlugosc i sekcje obowiazkowe).

### Faza E - Monetyzacja i eksperymenty

- TASK-E01 [OPEN] Dodac testy ranking premium weighting (top 3 determinism + tie behavior).
- TASK-E02 [OPEN] Dodac walidacje disclosure i fallback CTA afiliacja->lead.
- TASK-E03 [OPEN] Dodac eksperyment A/B dla sekcji CTA z event contract i raportem wyniku.
- TASK-E04 [OPEN] Dodac testy balansu ads density vs CTA visibility (mobile first).
- TASK-E05 [OPEN] Dodac dashboard mix revenue per page type (ads/affiliate/lead).

### Faza F - Frontend, UX i wydajnosc

- TASK-F01 [OPEN] Dodac testy interakcji search UX (intent mapping + debounce + empty state).
- TASK-F02 [OPEN] Dodac Lighthouse gate dla mobile (CWV budzet i trend tygodniowy).
- TASK-F03 [OPEN] Dodac testy regresji JS bez jQuery (critical flows).
- TASK-F04 [OPEN] Dodac visual smoke dla kluczowych template parts (hero, sections, CTA).
- TASK-F05 [OPEN] Dodac audyt a11y dla formularzy i nawigacji mobilnej.

### Faza G - CI/CD, operacje i hardening

- TASK-G01 [OPEN] Dodac pipeline nightly: smoke + unit + integration + load suite + raport.
- TASK-G02 [OPEN] Dodac gate SLO dla peak scenario i trend p95/p99 tydzien-do-tygodnia.
- TASK-G03 [OPEN] Dodac release runbook: preflight, deploy, rollback, post-deploy checks.
- TASK-G04 [OPEN] Dodac policy branch protection + required checks dla `main`.
- TASK-G05 [OPEN] Dodac checklist incydentowa dla awarii `/track` i lead submit.

### Faza H - Domkniecie projektu (Definition of Done)

- TASK-H01 [OPEN] Zakonczyc wszystkie taski A-G ze statusem DONE.
- TASK-H02 [OPEN] Potwierdzic zielony pipeline przez 7 kolejnych dni.
- TASK-H03 [OPEN] Potwierdzic metryki produkcyjne: CR lead, EPC, RPM, CWV w targetach.
- TASK-H04 [OPEN] Zamknac dokumentacje operacyjna i przekazac runbooki do utrzymania.
- TASK-H05 [OPEN] Oznaczyc release finalny i zrobic freeze zmian krytycznych.
