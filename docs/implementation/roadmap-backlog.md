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
- Kolejny krok: uruchomic testy integracyjne FE po aktywacji motywu.

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
