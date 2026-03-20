# Roadmap i Backlog Wdrozenia

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
