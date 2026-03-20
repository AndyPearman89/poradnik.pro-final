# Biblioteka Promptow Wykonawczych

## Prompt 1 - Full Theme Build

Zadanie:

Zbuduj kompletny theme poradnik.pro z pelna struktura katalogow, warstwa serwisowa w inc, komponentami UI i szablonami stron konwersyjnych. Integruj dane przez /wp-json/peartree/v1/*, bez jQuery, z naciskiem na mobile conversion.

Wymagania:

- modularna architektura (separacja UI/logika/dane)
- kompletny design system (tokeny, grid, komponenty)
- search.js live suggestions
- leads.js z walidacja, honeypot, retry
- tracking.js dla cta/search/lead/scroll
- schema i SEO dynamiczne

Wynik:

- pelne pliki bez placeholderow
- uruchamialny theme poradnik.pro

## Prompt 2 - Conversion Optimization Pass

Zadanie:

Przeprowadz pass optymalizacji konwersji dla wszystkich templatek. Dodaj CTA co 2 sekcje, trust badges, urgency bloki, sticky CTA mobile i popraw hierarchy contentu.

Wymagania:

- brak regresji wydajnosci
- zachowanie semantyki SEO
- kompatybilnosc z API payload

Wynik:

- patch z opisem impactu na CR
- lista zmian per template

## Prompt 3 - SEO and Schema Hardening

Zadanie:

Wdrozenie dynamicznych meta title, description, canonical oraz schema Article, FAQPage, ItemList, LocalBusiness dla odpowiednich typow stron.

Wymagania:

- dane schema pochodza z realnych danych strony
- brak duplikacji canonical
- testowalna implementacja serwisowa

Wynik:

- komplet plikow SeoService/SchemaService + hooki

## Prompt 4 - Search Experience Build

Zadanie:

Zaimplementuj AI-ready wyszukiwarke natural language z debounce, mappingiem wynikow na guides/specialists/rankings i dropdownem autocomplete.

Wymagania:

- endpoint /peartree/v1/search?q=
- obsluga bledow i pustych wynikow
- klikalne elementy, szybkie interakcje mobilne

Wynik:

- search.js + UI integration + tracking event

## Prompt 5 - Lead Engine Production

Zadanie:

Stworz produkcyjny lead engine frontendu: formularze, walidacje, routing payloadu do /peartree/v1/leads, stany loading/success/error, retry i anti-spam.

Wymagania:

- kompatybilny payload backendowy
- odpornosc na timeout i 5xx
- eventy trackingowe dla submit success/fail

Wynik:

- leads.js + reusable helpery + szablony formularzy

## Prompt 6 - Performance Budget Pass

Zadanie:

Zoptymalizuj frontend pod Core Web Vitals i realny mobile speed: defer JS, lazy image, mniejsze CSS, krytyczne style i ograniczenie repaint/reflow.

Wymagania:

- bez degradacji UX i konwersji
- no jQuery
- minimalny payload startowy

Wynik:

- raport zmian i finalny budzet wydajnosci

## Prompt 7 - Hybrid Monetization Orchestrator

Zadanie:

Wdrozenie orchestratora monetyzacji laczacego AdSense, afiliacje i leady na podstawie poziomu intencji uzytkownika oraz typu strony.

Wymagania:

- routing low->ads, mid->afiliacja, high->lead
- page-level policy dla poradnik/ranking/lokalna
- zachowanie UX mobile i zgodnosci SEO

Wynik:

- komplet zasad renderingu + tracking KPI revenue

## Prompt 8 - Affiliate and Ad UX System

Zadanie:

Zaimplementuj komponenty monetyzacyjne frontendowe: boxy afiliacyjne, disclosure, sloty reklamowe i reguly anti-clutter na mobile.

Wymagania:

- legalne oznaczenie tresci partnerskich
- placement reklam pomiedzy sekcjami informacyjnymi
- fallback lead CTA w widokach mid intent

Wynik:

- zestaw komponentow UI i reguly ich osadzania

## Prompt 9 - Revenue Analytics and Attribution

Zadanie:

Dodaj warstwe analityczna dla pelnego funnelu monetyzacji: ads clicks, affiliate clicks, lead submits, scroll depth i attribution kanalu ruchu.

Wymagania:

- jednolity event schema
- gotowosc pod GA i Meta Pixel
- raport revenue per session z podzialem na intent

Wynik:

- tracking.js + dokument eventow + mapowanie KPI

## Prompt 10 - Full Autonomous System Builder

Zadanie:

Uruchom kompletny build systemu Poradnik.pro jako platformy SEO + monetization z warstwami AdSense, afiliacji i lead engine, wraz z automatyzacja i trackingiem.

Pelna wersja promptu wykonawczego:

- docs/prompts/poradnik-pro-full-autonomous-system-builder.md
