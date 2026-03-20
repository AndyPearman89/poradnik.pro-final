# Frontend Specification - Poradnik.pro

## 1. Cel frontendu

Frontend ma realizowac model:

PROBLEM -> GUIDE -> SPECIALIST -> LEAD

Priorytet: wysoka konwersja i szybka droga do kontaktu.

Model biznesowy frontendu jest hybrydowy:

- low intent: AdSense
- mid intent: afiliacja
- high intent: lead

## 2. Struktura theme poradnik.pro

Docelowa struktura:

- poradnik.pro/
- style.css
- functions.php
- assets/css: variables.css, base.css, layout.css, components.css, utilities.css, dark.css
- assets/js: core.js, search.js, leads.js, ui.js, tracking.js
- assets/images
- inc: App.php, Enqueue.php, ApiClient.php, LeadService.php, SearchService.php, SeoService.php, SchemaService.php, UiService.php, PerformanceService.php
- template-parts: hero, sections, cards, components
- templates: front-page, single-guide, single-ranking, archive-ranking, single-specialist, archive-local, single-question

## 3. Design system

### Tokeny

- kolory: primary, secondary, accent, bg, text
- spacing scale: 4/8/12/16/24/32/48/64
- typography scale: fluid, mobile-first
- elevation: shadow-sm/md/lg
- radius: 8/12/16

### Komponenty

- button: primary, secondary, ghost
- card: guide, specialist, ranking
- badge: FREE, PREMIUM, PREMIUM+
- rating stars
- cta blocks
- alerty i urgency blocks

### Layout

- responsywny grid
- kontener max-width
- sticky CTA mobile
- duze tap targets

## 4. Wymagania dla stron

### Home (front-page)

Sekcje:

1. Hero z wyszukiwaniem i dual CTA
2. Trending problems
3. Problem grid
4. Guides grid (API)
5. Rankings (top 3 + lista)
6. Specialists
7. Global CTA

Dodatkowe zasady monetyzacyjne home:

- ads slots tylko w sekcjach informacyjnych
- bloki afiliacyjne dla zapytan produktowych
- lead-first CTA dla intencji uslugowej i lokalnej

### Single Guide

- auto TOC z naglowkow
- segmentacja tresci
- CTA co 2 sekcje
- FAQ + schema FAQPage
- mieszany model: content + ads + afiliacja + CTA lead

### Ranking

- top 3 wyroznione
- lista rankingowa
- comparison table
- affiliate CTA
- lead CTA jako fallback konwersyjny

### Specialist

- profil i uslugi
- opinie i rating UI
- sticky CTA do lead form

### Local SEO

- slug typu usluga-miasto
- listing specjalistow
- powiazane poradniki i ranking
- CTA kontaktowy
- lead-first layout (high intent)

### Q&A

- pytanie
- lista odpowiedzi
- CTA do specjalisty

## 5. Integracja API

Wszystkie dane pobierane z REST API:

- GET /peartree/v1/guides
- GET /peartree/v1/listings
- GET /peartree/v1/rankings
- GET /peartree/v1/search?q=
- POST /peartree/v1/leads

Zasady:

- wspolny API client
- mapowanie odpowiedzi do modelu UI
- fallback i obsluga bledow
- mapowanie intent -> monetization mode

## 6. Lead engine

Formularz:

- name
- email/phone
- problem
- location
- honeypot

Funkcje:

- walidacja
- loading state
- retry logic
- sukces/error state
- tracking event

## 7. Search engine

- live search input
- debounce
- wynik grupowany: guides/specialists/rankings
- dropdown autocomplete
- tracking zapytan

## 8. SEO i schema

- dynamiczny title/description/canonical
- schema: Article, FAQPage, ItemList, LocalBusiness
- internallinking miedzy poradnikami, rankingami i specjalistami

## 9. Performance

- defer JS
- lazy load obrazow
- preconnect font hosts
- minimalny JS payload
- minimalne operacje DOM

## 10. Conversion optimization

- CTA co 2 bloki
- trust badges i review snippets
- urgency elements
- czytelne, szybkie sciezki akcji na mobile

## 11. Hybrid monetization UX

- poradnik mixed: 50% content, 25% ads, 25% afiliacja+CTA
- ranking: 60% afiliacja, 30% lead, 10% ads
- lokalna: 80% lead, 20% ads

Wymagania UI:

- komponent Polecany produkt z linkiem afiliacyjnym
- oznaczenia tresci sponsorowanej/partnerskiej
- anti-clutter ads policy na mobile
- zawsze co najmniej jedna sciezka monetyzacji na widoku

## 12. Marketing channels integration

- landing i tresc gotowe pod SEO, Discover i social short-form
- deep linki do rankingow i stron lokalnych
- modularne bloki CTA pod kampanie performance

## 13. Enterprise monetization playbook

- Core flow: User -> Content -> Decision -> Monetization
- Decision routing: low=AdSense, mid=afiliacja, high=lead
- AdSense placement: po 2 akapicie, po H2, koniec artykulu
- Afiliacja placement: TOP3, tabela porownawcza, rekomendowany box
- Lead placement: CTA + formularz + routing do 3-5 firm
- Struktura stron:
	- poradnik: 50% content, 25% ads, 25% afiliacja+CTA
	- ranking: 60% afiliacja, 30% lead, 10% ads
	- lokalna: 80% lead, 20% ads
- KPI target:
	- ads RPM 10-40 PLN, CTR 1-3%
	- afiliacja CR 2-10%, EPC
	- lead CR 3-15%, wartosc 10-200+ PLN

## 14. SEO system enterprise

- Core model SEO: problem -> traffic -> content -> monetyzacja
- Filary: Problem SEO, Local SEO, Ranking SEO, Comparison SEO, Q&A SEO
- Keyword architecture:
	- Problem keywords
	- Local keywords
	- Ranking keywords
	- Comparison keywords
	- Q&A keywords
- Programmatic generator:
	- problem + rozwiazanie
	- usluga + miasto
	- ranking + miasto
	- product vs product
- URL policy:
	- /poradnik/...
	- /ranking/...
	- /uslugi/...
- Technical baseline:
	- szybka strona i mobile-first
	- clean HTML
	- schema Article/FAQPage/LocalBusiness/ItemList
	- automaty meta, schema i linkowania
- Internal linking:
	- guides -> rankings -> local pages
	- kazda strona linkuje do powiazanych poradnikow, rankingow i lokalnych
- Delivery plan:
	- 0-30 dni: 100 poradnikow, 20 rankingow, 50 lokalnych
	- 30-90 dni: skala do 1000 stron
	- 90+ dni: automation hardening i scaling

Ryzyka do kontroli:

- thin content
- brak internal linking
- slabe UX
- brak widocznego CTA

## 15. Complete system operating model

- Model: Traffic -> Content -> Intent -> Monetization -> Revenue
- Warstwy monetyzacji: AdSense, Affiliate, Lead Engine
- Intent mapping: low=AdSense, mid=Affiliate, high=Lead
- Page behavior:
	- Guide: content + ads + CTA
	- Ranking: affiliate-heavy + lead support
	- Local: lead-focused
- Automation set:
	- AI content
	- auto ads placement
	- auto affiliate links
	- auto internal linking
- Core channels:
	- SEO (core)
	- YouTube Shorts
	- Pinterest
	- Discover
- KPI compact:
	- SEO: traffic, keywords
	- Ads: RPM
	- Affiliate: EPC
	- Lead: CR, revenue
