# Poradnik.pro - System Blueprint

## 1. Tozsamosc produktu

Poradnik.pro jest platforma typu:

INTENT ENGINE -> CONTENT ENGINE -> TRUST ENGINE -> MARKETPLACE -> LEAD ENGINE -> REVENUE ENGINE

To nie jest blog redakcyjny. To system konwersji ruchu w leady i przychod, oparty o model hybrydowy (AdSense + afiliacja + lead).

## 2. Model biznesowy

Sciezka uzytkownika:

PROBLEM -> PORADNIK/RANKING -> SPECJALISTA -> LEAD -> REVENUE

Glowne zrodla przychodu:

- AdSense na ruchu low intent
- afiliacja na intencji zakupowej
- leady (open/targeted/exclusive)
- abonamenty specjalistow
- visibility boost (premium placement)
- sponsorowane pozycje
- SaaS i white-label

Priorytetyzacja monetyzacji po intencji:

- low intent -> AdSense
- mid intent -> afiliacja
- high intent -> lead

## 3. Warstwy systemu

### Layer 1: Acquisition Engine

Ruch z:

- SEO (programmatic + long-tail)
- lokalne intencje
- przyszlosciowo AI/chat/voice

Typy stron:

- poradnik
- ranking
- porownanie
- Q&A
- landing lokalny

### Layer 2: Intent Engine

Przetwarza zapytanie naturalne i mapuje na:

- kategorie
- podkategorie
- intencje
- pilnosc

Routing intencji do modelu przychodu:

- low: informacja + ads slots
- mid: rekomendacje produktowe + affiliate CTA
- high: specialist matching + lead form

### Layer 3: Content Engine

Treści narzedziowe:

- szybka odpowiedz
- kroki realizacji
- decyzja DIY vs specjalista

### Layer 4: Trust Engine

Sygnały zaufania:

- oceny
- opinie
- liczba realizacji
- badge FREE/PREMIUM/PREMIUM+

### Layer 5: Marketplace Engine

Dane ofertowe:

- profile specjalistow
- uslugi
- lokalizacja
- dostepnosc

### Layer 6: Lead Engine

Krytyczny pipeline:

- walidacja
- routing
- dystrybucja
- statusowanie
- domkniecie

### Layer 7: Revenue Engine

Optymalizacja LTV i marzy:

- miks lead pricing
- premium weighting
- affiliate intensity
- ads density control i RPM optimization
- revenue per session orchestration

## 4. Wymagania technologiczne

- WordPress latest (multisite-ready)
- poradnik.pro theme (custom, standalone)
- PHP 8+
- Vanilla JS (bez jQuery)
- REST API: /wp-json/peartree/v1/*
- architektura modularna z warstwa serwisowa

## 5. Kluczowe API

- /peartree/v1/search
- /peartree/v1/leads
- /peartree/v1/listings
- /peartree/v1/guides
- /peartree/v1/rankings

## 6. Kluczowe KPI

- CR (conversion rate)
- CPL (cost per lead)
- lead acceptance rate
- response time specjalistow
- SEO growth i rank keywords
- RPM i ad CTR
- EPC afiliacyjny i affiliate CTR
- revenue per user/session

## 7. Ryzyka krytyczne

- duzy ruch i niska konwersja (problem UX/funnel)
- wysoka konwersja i niska jakosc leadow (problem routing/scoring)
- niska indeksacja stron programmatic (problem SEO quality)
- wolna strona mobilna (problem performance)
