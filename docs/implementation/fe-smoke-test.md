# Frontend Smoke Test

## Cel

Szybka walidacja, że motyw FE jest uruchomiony i poprawnie integruje się z tracking endpoint'em.

## Narzędzie

- scripts/smoke-test-fe.mjs (Node, bez zależności)

## Przebieg testu

1. Pobiera stronę główną (`/`)
2. Sprawdza, czy zwrócono status 200
3. Waliduje obecność oczekiwanych markerów HTML (poradnik, wp-json)
4. PostPayloaduje test event do `/wp-json/peartree/v1/track`
5. Waliduje kod odpowiedzi 200 i pole `success` w JSON
6. W trybie `--strict-runtime` failuje na markerach runtime error (Fatal/Parse/Warning/Notice/Deprecated) w homepage i odpowiedzi `/track`

## Uruchomienie

```bash
node scripts/smoke-test-fe.mjs --base http://localhost:8080
```

Tryb twardej bramki runtime (zalecany w CI):

```bash
node scripts/smoke-test-fe.mjs --base http://localhost:8080 --strict-runtime
```

## Wykorzystanie w CI

Test uruchamiany jest w workflow po aktywacji motywu i przed load testem, aby upewnić się, że:

- motyw został poprawnie zainstalowany
- strona główna odpowiada prawidłowo
- tracking endpoint jest dostępny

Jeśli test się nie powiedzie, workflow zatrzymuje się i nie uruchamia load testu.

W CI używany jest tryb `--strict-runtime`, więc każde wykrycie markerów błędu runtime zatrzymuje pipeline.

## Przykładowy output

```text
Frontend smoke test
base: http://localhost:8080

✓ Home page loads successfully (status 200)
✓ Expected HTML markers present

✓ Tracking endpoint accepts POST events (status 200)
✓ Tracking response confirmed success

Overall: PASS
```
