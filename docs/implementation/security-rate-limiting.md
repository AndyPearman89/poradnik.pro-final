# Security & Rate Limiting

## Endpoint /track

### Cel

Endpoint `/wp-json/peartree/v1/track` akceptuje zdarzenia telemetryki z frontendu i rejestruje je w bazie danych. Poniższe wskazówki zabezpieczają endpoint przed nadużyciami.

### Mechanizmy bezpieczeństwa

#### 1. Permission Callback

Endpoint ma callback walidacyjny (`checkTrackingPermission()`), który w bieżącej wersji zwraca `true` dla publicznego dostępu (docelowo: validation origin + rate limiting).

#### 2. Security Headers

Każda odpowiedź zawiera:

```http
Cache-Control: no-store, must-revalidate, max-age=0
Pragma: no-cache
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
```

#### 3. Input Sanitization

Wszystkie pola payload'u są czyszczone przy ingest:

- `eventName` → `sanitize_key()`
- `source` / `channel` → `sanitize_key()`
- Pozostałe pola → dostępne w debug logach

#### 4. Rate Limiting

Konstanta `RATE_LIMIT_REQUESTS_PER_MIN = 300` predefiniuje próg dla implementacji rate limitera.

**Opcje wdrożenia:**

- **Serwer**: LiteSpeed Cache, nginx rate limiting
- **PHP**: Custom middleware lub WP plugin (np. Simple History, Jetpack Security)
- **CDN**: Cloudflare, AWS WAF

#### 5. Audit Logging

Gdy `WP_DEBUG` jest włączony (dev/staging):

```php
[Analytics] event=cta_click source=affiliate timestamp=2026-03-20 12:34:56
```

### Production Checklist

- [ ] Włączyć Rate Limiting na poziomie serwera
- [ ] Konfigurować whitelist origin dla CORS (jeśli wymagane)
- [ ] Monitorować logi / integrować z SIEM
- [ ] Testować load bez nadmiernych obciążeń (baseline p95 OK)
- [ ] Backupować dane KPI regularnie (cron job)

### Powiązane

- [Testy obciążeniowe](./track-load-testing.md)
- [Smoke test FE](./fe-smoke-test.md)
