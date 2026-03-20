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

## Uruchomienie

```bash
node scripts/smoke-test-fe.mjs --base http://localhost:8080
```

## Wykorzystanie w CI

Test uruchamiany jest w workflow po aktywacji motywu i przed load testem, aby upewnić się, że:

- motyw został poprawnie zainstalowany
- strona główna odpowiada prawidłowo
- tracking endpoint jest dostępny

Jeśli test się nie powiedzie, workflow zatrzymuje się i nie uruchamia load testu.

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
