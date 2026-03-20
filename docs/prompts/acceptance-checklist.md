# Acceptance Checklist

## A. Architektura

- [ ] Warstwa serwisowa w inc rozdziela odpowiedzialnosci
- [ ] Template files nie zawieraja ciezkiej logiki biznesowej
- [ ] API client jest wspolny i reuzywalny

## B. UX i Conversion

- [ ] CTA wystepuje regularnie (maks. co 2 sekcje)
- [ ] Sticky mobile CTA dziala na stronach konwersyjnych
- [ ] Trust signals (oceny/opinie/badge) widoczne above-the-fold
- [ ] Formularz lead ma szybki i zrozumialy feedback

## C. Search i Leads

- [ ] Live search dziala z debounce i grouped results
- [ ] Lead form waliduje wymagane pola i honeypot
- [ ] Retry logic aktywny przy timeout i bledach chwilowych
- [ ] Bledy API sa czytelnie obslugiwane

## D. SEO i Content

- [ ] Dynamiczny title, description i canonical dla typow stron
- [ ] Schema Article/FAQPage/ItemList/LocalBusiness jest poprawna
- [ ] TOC na poradnikach generuje sie automatycznie
- [ ] Local landingi lacza specialist + guides + ranking

## E. Performance

- [ ] Brak zaleznosci jQuery
- [ ] Skrypty defer, obrazy lazy
- [ ] Mobile UX zachowuje szybki TTI
- [ ] Minimalna liczba zapytan blokujacych render

## F. Tracking

- [ ] Event: cta_click
- [ ] Event: search_query
- [ ] Event: lead_submit_success
- [ ] Event: lead_submit_failure
- [ ] Event: scroll_depth milestones

## G. Hybrydowa Monetyzacja

- [ ] Segmentacja intent low/mid/high steruje modelem przychodu
- [ ] Low intent strony maja bezpieczny ads placement bez clutteru
- [ ] Mid intent strony maja boxy afiliacyjne i czytelne disclosure
- [ ] High intent strony maja lead-first CTA i formularz above-the-fold
- [ ] Co najmniej jedna sciezka monetyzacji jest widoczna na kazdym kluczowym widoku

## H. KPI Revenue

- [ ] Mierzone RPM i ad CTR
- [ ] Mierzone EPC i affiliate CTR
- [ ] Mierzony revenue per user/session
- [ ] Mierzony intent-to-monetization match rate

## I. Automation i Distribution

- [ ] Auto placement ads respektuje limity UX mobile
- [ ] Auto bloki afiliacyjne sa osadzane dla mid intent
- [ ] Auto CTA leadowe sa osadzane dla high intent
- [ ] Kanaly SEO/YouTube Shorts/Pinterest/Discover maja dedykowane deep-linki i tracking

## J. SEO Enterprise

- [ ] Filary Problem/Local/Ranking/Comparison/Q&A SEO sa pokryte typami stron
- [ ] Keyword architecture obejmuje klastry: problem/local/ranking/comparison/Q&A
- [ ] Programmatic generator dziala dla kombinacji problem+rozwiazanie, usluga+miasto, ranking+miasto
- [ ] URL policy jest spójna: /poradnik/, /ranking/, /uslugi/
- [ ] Technical SEO baseline (CWV mobile, schema, canonical, internal linking) jest spelniony
- [ ] Plan skalowania 0-30 i 30-90 dni ma mierzalne KPI wzrostu organicznego

## K. SEO Behavior i Risk Control

- [ ] Mierzone zachowanie: time on page i bounce rate
- [ ] Kazdy template ma widoczne CTA i sciezke konwersji
- [ ] Ryzyko thin content jest kontrolowane przez QA contentu
- [ ] Internal linking nie ma luk miedzy guide/ranking/local
