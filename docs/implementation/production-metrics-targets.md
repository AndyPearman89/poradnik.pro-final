# Production Metrics Targets (TASK-H03)

## Cel

Definicja i monitoring celow metryk produkcyjnych dla `poradnik.pro-final`.
Metryki pokrywaja cztery kluczowe obszary: konwersja, monetyzacja i wydajnosc frontendu.

---

## 1. Business KPIs

### CR – Conversion Rate (Wskaznik konwersji)

**Definicja**: Stosunek udanych submisji formularza leadowego do unikalnych sesji uzytkownikow.

```
CR = lead_success_count / unique_sessions
```

| Poziom       | Wartosc | Opis                              |
|-------------|---------|-----------------------------------|
| **Target**  | ≥ 2.0%  | Minimalny akceptowalny poziom     |
| **Good**    | ≥ 3.5%  | Dobry wynik dla niszy finansowej  |
| **Excellent** | ≥ 5.0% | Wynik swiatowej klasy             |
| **Alert**   | < 1.5%  | Wymaga natychmiastowej interwencji |

**Zrodla danych**:
- `wp_options.poradnik_pro_kpi_store` → `revenue.lead_success`
- `/wp-json/peartree/v1/kpi/summary` endpoint
- Google Analytics 4 / Conversion tracking

**Czynniki wplywajace**:
- Jakosc ruchu (SEO vs direct vs paid)
- Widocznosc CTA (patrz TASK-E03, E04)
- Formularz leadowy (TASK-C01)
- Landing page UX (Core Web Vitals)

---

### EPC – Earnings Per Click (Zarobek na klikniecie)

**Definicja**: Sredni przychod generowany na kazde klikniecie w link afiliacyjny.

```
EPC = estimated_affiliate_revenue / affiliate_clicks
```

| Poziom       | Wartosc PLN | Opis                             |
|-------------|------------|----------------------------------|
| **Target**  | ≥ 0.05 PLN | Minimalny akceptowalny poziom    |
| **Good**    | ≥ 0.15 PLN | Dobry wynik dla finansow/ubezp.  |
| **Excellent** | ≥ 0.30 PLN | Wysoka jakosc ruchu + ofert      |
| **Alert**   | < 0.02 PLN | Niski intent lub slabe oferty    |

**Zrodla danych**:
- `wp_options.poradnik_pro_kpi_store` → `revenue.affiliate_clicks`, `revenue.estimated_affiliate_revenue`
- Raporty sieci afiliacyjnych (Tradedoubler, WebePartners, itp.)

**Czynniki wplywajace**:
- Mix programow afiliacyjnych (TASK-E02)
- Targeting i kontekst tresci
- A/B CTA eksperymenty (TASK-E03)
- Sezonowosc i kampanie partnerow

---

### RPM – Revenue Per Mille (Przychod na 1000 wyswietlen)

**Definicja**: Laczny szacowany przychod (affiliate + lead) na 1000 odslon strony.

```
RPM = (estimated_total_revenue / pageviews) * 1000
```

Gdzie `estimated_total_revenue = estimated_affiliate_revenue + estimated_lead_revenue`

| Poziom       | Wartosc PLN | Opis                              |
|-------------|------------|-----------------------------------|
| **Target**  | ≥ 2.00 PLN | Minimalny akceptowalny poziom     |
| **Good**    | ≥ 5.00 PLN | Dobry wynik dla serwisu doradczego |
| **Excellent** | ≥ 10.00 PLN | Premium – silny intent + oferty  |
| **Alert**   | < 1.00 PLN | Revenue mix wymaga przegladu      |

**Zrodla danych**:
- KPI store + analytics pageviews
- `scripts/integration-test-kpi-dashboard.mjs`
- Revenue mix dashboard (TASK-E05)

**Segment breakdown** (oczekiwany target mix):
| Typ strony          | RPM Target |
|--------------------|-----------|
| Ranking finansowy  | ≥ 8 PLN   |
| FAQ / pytanie      | ≥ 3 PLN   |
| Lokalna strona     | ≥ 2 PLN   |
| Archiwum           | ≥ 1 PLN   |

---

## 2. Core Web Vitals (CWV)

Zgodnie z progami **Google Search Central** dla kategori "Good" (mobile, lab-based via Lighthouse).

### LCP – Largest Contentful Paint

| Kategoria  | Prog       |
|-----------|-----------|
| **Good**   | ≤ 2500 ms |
| Needs Imp. | 2500–4000 ms |
| Poor       | > 4000 ms |

**Target produkcyjny**: **≤ 2500 ms** (mobile, Lighthouse lab)

### CLS – Cumulative Layout Shift

| Kategoria  | Prog   |
|-----------|--------|
| **Good**   | ≤ 0.10 |
| Needs Imp. | 0.10–0.25 |
| Poor       | > 0.25 |

**Target produkcyjny**: **≤ 0.10**

### TBT – Total Blocking Time (proxy FID/INP w lab)

| Kategoria  | Prog      |
|-----------|-----------|
| **Good**   | ≤ 300 ms  |
| Needs Imp. | 300–600 ms |
| Poor       | > 600 ms  |

**Target produkcyjny**: **≤ 300 ms** (mobile, Lighthouse lab)

> **Uwaga**: TBT w warunkach laboratoryjnych (Lighthouse) jest proxy dla INP/FID.
> W produkcji nalezy uzupelnic o CrUX (Chrome User Experience Report) lub RUM.

---

## 3. Monitoring i walidacja

### Skrypt sprawdzajacy

```bash
# Sprawdz wszystkie metryki wzgledem targetow:
node scripts/check-production-metrics.mjs

# Z nadpisaniem targetow:
node scripts/check-production-metrics.mjs --min-cr 0.025 --min-epc 0.08 --min-rpm 3.0

# Podaj dane metryk bezposrednio (plik JSON):
node scripts/check-production-metrics.mjs --metrics-file /path/to/metrics.json

# Emituj raport JSON:
node scripts/check-production-metrics.mjs --json --report-out docs/implementation/reports/metrics-check.json
```

### Format pliku metryk (`--metrics-file`)

```json
{
  "cr":  0.023,
  "epc": 0.12,
  "rpm": 4.50,
  "lcp": 2100,
  "cls": 0.05,
  "tbt": 180
}
```

### Automatyczna walidacja CWV

Skrypt automatycznie odczytuje najnowszy wpis z:
`docs/implementation/reports/lighthouse-mobile-history.json`

Lighthouse mobile gate jest uruchamiany via:
```bash
node scripts/integration-test-lighthouse-mobile.mjs --base http://127.0.0.1:8080
```

---

## 4. Harmonogram pomiarow

| Czestotliwosc | Akcja                                   | Odpowiedzialny |
|--------------|-----------------------------------------|----------------|
| **Codziennie** | Nightly pipeline (CWV via Lighthouse) | CI/CD (auto)   |
| **Tygodniowo** | Przeglad CR/EPC/RPM                   | Tech Lead       |
| **Miesiecznie** | Full metrics review + trend analysis | Product Owner   |
| **Kwartalnie** | Rewizja targetow w tym dokumencie     | Product Owner   |

---

## 5. Alerty i eskalacja

| Metryka | Alert Level   | Akcja                                          |
|--------|--------------|------------------------------------------------|
| CR     | < 1.5%        | Natychmiastowy przeglad CTA, formularz, ruch   |
| EPC    | < 0.02 PLN    | Przeglad programow afiliacyjnych               |
| RPM    | < 1.00 PLN    | Revenue mix audit                              |
| LCP    | > 4000 ms     | Performance audit, CDN, image optimization     |
| CLS    | > 0.25        | Layout stability fix (fonty, obrazy, ads)      |
| TBT    | > 600 ms      | JS bundle audit, code splitting                |

---

## 6. Definicja ukonczenia TASK-H03

TASK-H03 jest DONE gdy:

1. Ten dokument istnieje z zdefiniowanymi targetami.
2. `scripts/check-production-metrics.mjs` dziala i waliduje metryki.
3. Produkcyjne metryki sa zbierane (przynajmniej CWV z Lighthouse history).
4. Alerty i harmonogram pomiarow sa zdefiniowane.

---

## 7. Referencje

- [Google Web Vitals Thresholds](https://web.dev/vitals/)
- [TASK-E03] A/B CTA eksperyment + raport
- [TASK-E04] Ads density vs CTA visibility tests (mobile)
- [TASK-E05] Revenue mix dashboard per page type
- [TASK-F02] Lighthouse mobile gate + trend
- `scripts/integration-test-lighthouse-mobile.mjs`
- `scripts/integration-test-kpi-dashboard.mjs`
- `docs/implementation/reports/lighthouse-mobile-history.json`
