# Content + FAQ Prompt Pipeline

## Cel

Skalowanie tresci i FAQ przy zachowaniu intent -> monetization routing.

## Wejscie

- temat glowny
- typ intentu: low/mid/high
- lokalizacja (opcjonalnie)
- format strony: poradnik/ranking/local/q&a

## Etap 1: Brief SEO

Prompt:
"Wygeneruj brief SEO dla tematu {topic} i intentu {intent}. Zwracaj: keyword cluster, search intent, FAQ seed, outline H2/H3, propozycje internal links."

Wyjscie:
- clusters
- outline
- faq_seed
- internal_links_seed

## Etap 2: Draft Content

Prompt:
"Na podstawie briefu wygeneruj draft strony typu {format}. Utrzymaj mobile-first readability, krotkie akapity, jasne CTA."

Wyjscie:
- draft_content
- cta_points
- trust_elements

## Etap 3: FAQ Expansion

Prompt:
"Rozszerz FAQ seed do 6-10 pytan i odpowiedzi. Oznacz pytania pod high intent i pod local intent."

Wyjscie:
- faq_items
- high_intent_flags

## Etap 4: Monetization Routing

Prompt:
"Mapuj sekcje tresci do modelu monetyzacji low=ads, mid=affiliate, high=lead. Zwracaj placement plan i disclosure points."

Wyjscie:
- placement_plan
- disclosure_points
- fallback_cta

## Etap 5: QA Gate

Prompt:
"Sprawdz tresc pod: thin content risk, duplicate risk, brak CTA, brak internal linking, brak schema opportunities. Zwracaj tylko lista poprawek."

Wyjscie:
- qa_fix_list

## Etap 6: Publish Package

Prompt:
"Przygotuj paczke publikacyjna: title, meta description, canonical, slug, schema hints, internal links list, tracking events."

Wyjscie:
- meta_package
- schema_hints
- tracking_contract

## Minimalne KPI na strone

- widoczny CTA above-the-fold
- co najmniej 3 internal links (guide/ranking/local)
- minimum 1 sciezka monetyzacji zgodna z intentem
- FAQ gotowe pod schema FAQPage (gdy format pozwala)
