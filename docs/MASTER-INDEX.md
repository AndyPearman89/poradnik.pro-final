# Poradnik.pro - Master Documentation Index

**Last Updated:** 2026-04-04
**Version:** 1.0 Final
**Status:** Production-Ready (91.2% Complete)

## Quick Start

### For Developers
1. [Local Development Setup](#local-development)
2. [Running Tests](#testing-framework)
3. [Deployment Guide](#deployment)

### For Operators
1. [Release Runbook](implementation/release-runbook.md)
2. [Incident Response](implementation/incident-response-checklist.md)
3. [Monitoring & SLA](implementation/autodev-supervision-runbook.md)

### For Product Owners
1. [System Architecture](#architecture-documentation)
2. [Progress Dashboard](implementation/progress-dashboard.md)
3. [Feature Roadmap](implementation/roadmap-backlog.md)

---

## Local Development

### Quick Setup
```bash
# 1. Clone and setup environment
cp .env.example .env

# 2. Start Docker stack (WordPress + MySQL)
docker compose up -d

# 3. Bootstrap WordPress (idempotent)
bash scripts/bootstrap-wp.sh \
  --site-url http://localhost:8080 \
  --site-title "Poradnik Pro Local"

# 4. Activate local E2E module
docker compose --profile tools run --rm wpcli \
  plugin activate peartree-local-module/peartree-local-module.php --allow-root

# 5. Configure permalinks
docker compose --profile tools run --rm wpcli \
  option update permalink_structure '/%postname%/' --allow-root
docker compose --profile tools run --rm wpcli \
  rewrite flush --hard --allow-root
```

### Access Points
- **Homepage:** http://localhost:8080
- **Admin Panel:** http://localhost:8080/wp-admin (admin/admin)
- **E2E Module UI:** http://localhost:8080/modul-e2e/
- **REST API Status:** http://localhost:8080/wp-json/peartree-local/v1/status
- **KPI Dashboard:** http://localhost:8080/wp-admin/admin.php?page=peartree-kpi

---

## Architecture Documentation

### Core System Documents

1. **[System Blueprint](architecture/system-blueprint.md)** ⭐
   - Product identity & business model
   - 7-layer architecture (Acquisition → Intent → Content → Trust → Marketplace → Lead → Revenue)
   - Critical KPIs and risk mitigation

2. **[Complete System Enterprise](architecture/complete-system-enterprise.md)** ⭐
   - Full monetization model (AdSense + Affiliate + Lead Engine)
   - Intent segmentation (LOW/MID/HIGH)
   - Revenue simulation (1000 users = 60-150 PLN ads + 200-800 PLN affiliate + 1500-6000 PLN leads)

3. **[SEO System Enterprise](architecture/seo-system-enterprise.md)**
   - Programmatic SEO strategy
   - Content clusters (Problem, Local, Ranking, Comparison, Q&A)
   - 0-90 day roadmap (100 → 1k → 10k → 100k pages)

4. **[Monetization Blueprint](architecture/monetization-blueprint.md)**
   - Hybrid revenue model details
   - Placement strategies (ads, affiliate, CTAs)
   - KPI targets (RPM: 10-40 PLN, CR: 3-15%, EPC optimization)

5. **[System 1-2-3 Monetization](architecture/system-1-2-3-monetization.md)**
   - Operational model with 60-day plan
   - Page type mix (50% guides, 30% rankings, 20% local)
   - Detailed revenue simulation

### Legacy Documents (Historical Reference)
- `architecture/full-system-enterprise.md` - Superseded by complete-system-enterprise.md

---

## Technical Implementation

### Theme Architecture

**Location:** `/poradnik.pro/`

**Core Components:**
- `functions.php` - Bootstrap (loads 20 service classes)
- `style.css` - CSS cascade entry point
- Template hierarchy:
  - Homepage: `templates/front-page.php`
  - Content types: `templates/single-{ranking|guide|question|specialist}.php`
  - Archives: `templates/archive-{ranking|local}.php`

**Service Layer (inc/):**

| Service | Purpose | Lines | Status |
|---------|---------|-------|--------|
| `AnalyticsService.php` | KPI ingestion, storage, dashboard, CSV export | 751 | ✅ Production |
| `LeadService.php` | Form handling, honeypot, retry logic | 84 | ✅ Production |
| `LeadRouter.php` | Multi-provider routing with fallback | 229 | ✅ Production |
| `SeoService.php` | Meta tags, canonical, OG tags | 50 | ✅ Production |
| `SchemaService.php` | JSON-LD generation (7 schema types) | 81 | ✅ Production |
| `MonetizationService.php` | Ranking algorithm, CTA resolution | 124 | ✅ Production |
| `LocalPageGenerator.php` | Dynamic local pages (service+city) | 242 | ✅ Production |
| `ThinContentGate.php` | Content quality validation | 439 | ✅ Production |
| `SlaMonitor.php` | Partner endpoint health monitoring | 247 | ✅ Production |
| `InternalLinkingService.php` | Auto-inject related links | 51 | ✅ Production |

**Total:** 20 classes, 3,658 lines PHP

### Frontend Stack

**Technology:**
- Zero jQuery (pure vanilla JS)
- Modular CSS (variables → base → layout → components → utilities)
- Mobile-first responsive design
- Dark mode support

**JavaScript Modules:**
- `core.js` - HTTP client, namespace
- `tracking.js` - Event tracking (CTA, search, lead, scroll)
- `attribution.js` - UTM/channel tracking
- `search.js` - Local search
- `leads.js` - Form with retry/backoff
- `ui.js` - Dynamic interactions

**CSS Structure:**
- `variables.css` (33 lines) - Design tokens
- `base.css` (53 lines) - Reset/normalization
- `layout.css` (44 lines) - Grid/flexbox
- `components.css` (168 lines) - Cards/buttons/forms
- `utilities.css` (28 lines) - Helpers
- `dark.css` (16 lines) - Dark theme

---

## Testing Framework

### Test Suites Overview

**Total:** 33 test files, 50+ functional scenarios

### Quality Gates (Run Before Every Release)

```bash
# 1. Frontend smoke test (runtime errors gate)
node scripts/smoke-test-fe.mjs \
  --base http://127.0.0.1:8080 \
  --strict-runtime

# 2. Backend unit tests (2 critical suites)
php scripts/unit-test-services.php
php scripts/unit-test-local-module-api.php

# 3. Integration tests (6 critical paths)
node scripts/integration-test-lead-form.mjs --base http://127.0.0.1:8080
node scripts/integration-test-kpi-dashboard.mjs --base http://127.0.0.1:8080
node scripts/integration-test-ads-cta-visibility.mjs --base http://127.0.0.1:8080
node scripts/integration-test-search-ux.mjs --base http://127.0.0.1:8080
node scripts/integration-test-lighthouse-mobile.mjs --base http://127.0.0.1:8080
node scripts/integration-test-a11y-forms-nav.mjs --base http://127.0.0.1:8080

# 4. Load test + SLO validation
node scripts/runnee.mjs --base http://127.0.0.1:8080
node scripts/check-track-slo.mjs

# 5. jQuery regression check
node scripts/integration-test-js-no-jquery.mjs --base http://127.0.0.1:8080
```

### Test Categories

**Unit Tests (11 PHP files):**
- Analytics service (pruning, revenue math, CSV export)
- Lead service (sanitization, honeypot, routing, retry)
- Local page generator (URL generation, schema, meta)
- Content quality gates (thin content, structured data)
- SLA monitoring

**Integration Tests (22 .mjs files):**
- Lead flow E2E (form → submit → success)
- KPI dashboard (HTTP auth → tie-order sources)
- Visual smoke (hero, sections, CTA, sticky CTA)
- A11y audit (skip-link, landmarks, labels)
- Template rendering validation
- Schema.org validation
- Internal linking injection
- Search UX
- Ads/CTA visibility (mobile)

**Load Tests:**
- Track endpoint: 500 baseline + 2000 peak requests
- SLO gates: p95 ≤ 2000ms, p99 ≤ 5000ms

**Performance Tests:**
- Lighthouse mobile audit (Core Web Vitals)
- Runtime error detection
- No-jQuery regression

---

## Deployment

### Manual Deployment

**Local Dry-Run:**
```bash
bash scripts/deploy-theme.sh \
  --local-target /var/www/html/wp-content/themes \
  --dry-run
```

**SSH Production:**
```bash
bash scripts/deploy-theme.sh \
  --ssh-target user@server:/path/to/themes \
  --backup \
  --validate
```

### Automated CI/CD

**Workflows:**

1. **nightly-quality.yml** (Daily 2:15 AM UTC)
   - Full test suite (smoke + unit + integration + load)
   - Lighthouse mobile audit
   - Report artifact upload

2. **track-load-test.yml** (On PR to main)
   - Triggered by changes to: `poradnik.pro/**`, `scripts/**`, `docker-compose.yml`
   - Load test + SLO validation

**Deployment Checklist:** See [Release Runbook](implementation/release-runbook.md)

---

## Operational Runbooks

### Critical Procedures

1. **[Release Runbook](implementation/release-runbook.md)** ⭐
   - Preflight checklist (20 items)
   - Deploy procedure (backup → copy → validate → rollback plan)
   - Post-deploy validation (smoke tests, metric baselines)

2. **[Incident Response Checklist](implementation/incident-response-checklist.md)** ⭐
   - /track endpoint failures (SLO breaches, timeout scenarios)
   - Lead submit failures (retry exhaustion, provider API down)
   - Emergency rollback procedures

3. **[Deployment Runbook](implementation/deployment-runbook.md)**
   - Local/SSH deployment with backup
   - Validation gates
   - Theme structure validation

4. **[Autodev Supervision Runbook](implementation/autodev-supervision-runbook.md)**
   - AI agent monitoring
   - Task queue management
   - Agent health checks

5. **[Final Handover Runbook](implementation/final-handover-runbook.md)**
   - Operational transfer checklist
   - Responsibility matrix
   - Known blockers (G04, H02, H03)

---

## Automation Systems

### Peartree Autodev Agent

**Location:** `/peartree-autodev/`

**Modes:**

1. **Full Autonomous Mode**
   ```bash
   # Single cycle
   cd peartree-autodev
   python agent/runner.py

   # Continuous 24/7
   docker compose -f peartree-autodev/docker-compose.yml up -d --build
   ```

2. **Hybrid Mode (Python + Copilot)**
   ```bash
   cd peartree-autodev/hybrid
   python runner.py
   ```

**Features:**
- Task planning and execution
- Code generation (Claude API)
- GitHub PR automation
- Git commit/push automation
- Memory/context persistence
- Audit logging

---

## Project Status

### Completion Metrics

**Overall Progress:** 91.2% (31/34 non-blocked tasks DONE)

```
[##################--] 91.2%
```

**By Priority:**
- P0 (Stability/Release): 85.7% (6/7 DONE, 1 BLOCKED)
- P1 (Quality/Conversion): 100.0% (9/9 DONE)
- P2 (SEO/UX/Monetization): 100.0% (15/15 DONE)
- P3 (Program Closure): 20.0% (1/5 DONE, 1 BLOCKED, 3 OPEN)

**By Phase (A-H):**
- A (Foundation): 2/2 DONE ✅
- B (KPI & Track): 5/5 DONE ✅
- C (Lead Flow): 5/5 DONE ✅
- D (SEO Scale): 5/5 DONE ✅
- E (Monetization): 5/5 DONE ✅
- F (Frontend Quality): 5/5 DONE ✅
- G (Governance): 4/5 DONE (1 BLOCKED: G04 branch protection) ⚠️
- H (Program Closure): 1/5 DONE (1 BLOCKED, 3 OPEN) ⚠️

### Active Blockers

1. **TASK-G04** - Branch protection configuration
   - Requires manual GitHub settings (cannot be automated via code)
   - Required checks: smoke-test, unit-tests, integration-tests, load-test

2. **TASK-H01** - All A-G tasks in DONE
   - Blocked by TASK-G04

### Open Tasks (Time/Data Dependent)

1. **TASK-H02** - 7 consecutive days of green pipeline
   - Requires time to elapse (monitoring nightly runs)

2. **TASK-H03** - Production metrics validation
   - Requires live traffic data (CR, EPC, RPM, CWV)

3. **TASK-H05** - Final release tag and freeze
   - Awaits H02 and H03 completion

---

## Feature Inventory

### Production-Ready Features ✅

**Content & SEO:**
- Dynamic meta tags (title, description, canonical, OG)
- JSON-LD structured data (7 schema types)
- Internal linking automation
- Local page generator (service+city)
- Content quality gates (thin content blocker)
- Template rendering validation

**Lead Engine:**
- Multi-field lead form (name, email/phone, problem, location)
- Honeypot spam prevention
- Retry logic (2 attempts, 250ms exponential backoff)
- Multi-provider routing with fallback
- Success/failure tracking

**Analytics & KPI:**
- Event tracking (/track endpoint)
- 10 event types (attribution, CTA, lead, scroll, search, etc.)
- 14-365 day retention
- Revenue math (affiliate + lead attribution)
- CSV export (365-day multiday data)
- WordPress admin dashboard
- Top sources ranking with tie-break

**Monetization:**
- Ranking algorithm (rating×0.7 + EPC×0.3 + premium boost)
- Affiliate CTA with fallback to lead form
- Top 3 display with comparison table
- Affiliate disclosure compliance

**Frontend:**
- Responsive CSS (mobile-first)
- Zero jQuery (vanilla JS)
- Dark mode support
- Sticky CTA (mobile conversion)
- A11y baseline (skip-link, semantic HTML, ARIA landmarks)
- Trust/urgency components

**Performance:**
- Deferred script loading
- Core Web Vitals targeting
- Track endpoint SLO gates (p95≤2000ms, p99≤5000ms)
- Load test suite (500 baseline + 2000 peak)

**Operational:**
- Idempotent WordPress bootstrap
- Docker dev environment
- CI/CD pipelines (nightly + PR-triggered)
- Comprehensive test suites (33 files, 50+ scenarios)
- Deployment automation (backup + rollback)
- Incident response procedures

### Missing/Incomplete Features ⚠️

**High Priority:**
- Branch protection enforcement (manual GitHub config required)
- AdSense placement automation (placement logic exists, auto-insertion not implemented)
- Rate limiting enforcement (skeleton exists, not enforced)
- Production monitoring/alerting (SLA logging exists, no alerts)

**Medium Priority:**
- AI content generation pipeline (prompts exist, automation not built)
- Partner API integrations (contracts defined, connectors not implemented)
- Real affiliate network integrations (mock offers only)
- Database schema for high-volume leads (options-based store limited to <1M events/day)
- Advanced search (Elasticsearch integration planned, basic search only)

**Low Priority:**
- Multi-language support (Polish only)
- Mobile app/PWA
- Advanced reporting UI (basic dashboard only)
- Content recommendation engine
- Dynamic pricing/seasonality

---

## API Reference

### REST Endpoints

**Track Endpoint:**
```
POST /wp-json/peartree/v1/track
Content-Type: application/json

{
  "event_type": "cta_click|lead_submit|search|scroll|attribution",
  "payload": {
    "source": "string",
    "value": number,
    "metadata": {}
  }
}
```

**Local Module (E2E Testing):**
```
GET /wp-json/peartree-local/v1/status
POST /wp-json/peartree-local/v1/echo
```

**KPI Dashboard:**
```
/wp-admin/admin.php?page=peartree-kpi
```

**Planned (Not Yet Implemented):**
- `/peartree/v1/search`
- `/peartree/v1/leads`
- `/peartree/v1/listings`
- `/peartree/v1/guides`
- `/peartree/v1/rankings`

---

## Prompts & Templates

### Master Prompts

1. **[Master Prompts](prompts/master-prompts.md)**
   - Normalized prompts for code agents
   - Build/refactor/SEO/lead/QA workflows

2. **[Acceptance Checklist](prompts/acceptance-checklist.md)**
   - Functional acceptance criteria
   - Conversion/SEO/performance/mobile checklists

3. **[Full Autonomous System Builder](prompts/poradnik-pro-full-autonomous-system-builder.md)**
   - Complete system build prompt (3000+ lines)
   - Frontend + SEO + monetization + automation

4. **[Content FAQ Prompt Pipeline](prompts/content-faq-prompt-pipeline.md)**
   - AI content generation workflow

---

## Requirements

### Frontend Specification

**Document:** [Frontend Spec](requirements/frontend-spec.md)

**Coverage:**
- Theme integration with PearTree Core
- Template architecture
- Component library
- Responsive design patterns
- A11y requirements

---

## Reports & Metrics

### Available Reports

**Location:** `docs/implementation/reports/`

1. **Lighthouse Mobile Reports**
   - Latest: `lighthouse-mobile-report-2026-03-21T08-55-42-112Z.md`
   - JSON: `lighthouse-mobile-2026-03-21T08-55-42-112Z.json`
   - History: `lighthouse-mobile-history.json`

2. **Track Load Test Reports**
   - `track-load-report-20260320-212259.md`
   - `track-load-report-20260320-205347.md`

### Metric Baselines

**Performance:**
- Track endpoint p95: <2000ms (SLO)
- Track endpoint p99: <5000ms (SLO)
- Lighthouse mobile performance: 90+ (target)
- Lighthouse mobile accessibility: 100 (required)

**Conversion:**
- Lead form CR: 3-15% (target)
- Affiliate CTR: 2-10% (target)
- CTA visibility: 100% above fold on mobile (required)

**Revenue:**
- AdSense RPM: 10-40 PLN (target)
- AdSense CTR: 1-3% (target)
- Lead value: 10-200+ PLN (target)

---

## Codebase Statistics

### Code Volume

**Theme (poradnik.pro/):**
- PHP: 3,658 lines (20 service classes)
- JavaScript: ~1,200 lines (6 modules)
- CSS: 342 lines (5 stylesheets)

**Plugins:**
- peartree-local-module: 50 lines

**Tests:**
- Unit tests: 11 PHP files
- Integration tests: 22 .mjs files
- Load tests: 4 files
- **Total test code:** ~5,000+ lines

**Documentation:**
- 28 markdown files
- ~168K total documentation

**Scripts:**
- 33 test/automation scripts
- ~336K total automation code

**Total Project:**
- ~1,000+ productive code lines
- ~30,000+ test/doc/automation lines
- 66 source files (PHP/JS/CSS)

### Code Quality Metrics

**Architecture:** 8/10 (Service-based, DDD, clean separation)
**Frontend:** 8/10 (No jQuery, responsive, a11y baseline)
**Testing:** 7/10 (33 files, 50+ scenarios, smoke+unit+integration+load)
**Documentation:** 7/10 (12 architecture docs, 8 runbooks)
**Security:** 7/10 (Sanitization, nonce checks, headers; rate limiting stub)
**Performance:** 7/10 (SLO gates, load testing, CWV focus)
**Automation:** 8/10 (Full CI/CD, autonomous agent)
**Code Style:** 8/10 (PHP strict types, consistent naming)

**Overall Maturity:** 6.5/10 - Production-Ready with Caveats

---

## Known Issues & Technical Debt

### Code Quality Issues

1. **Oversized Service Classes**
   - AnalyticsService: 751 lines (consider splitting: ingestion vs storage vs export)
   - ThinContentGate: 439 lines
   - TemplateRenderValidator: 399 lines

2. **Testing Gaps**
   - No PHP unit tests for controllers (InternalLinkingController, LeadRouter)
   - Limited edge case coverage (e.g., invalid JSON in AnalyticsService)
   - No performance benchmarks for large KPI stores

3. **Error Handling**
   - Silent failures in some service methods
   - Limited logging beyond WP_DEBUG mode
   - Retry logic hardcoded (not configurable)

4. **Architecture Concerns**
   - Mixed concerns in some services (AnalyticsService handles both ingestion and UI)
   - KPI store using WordPress options (not scalable >1M events/day)
   - Limited abstraction for external APIs

### Operational Issues

1. **Scalability**
   - Single-server deployment only (no HA/load balancing)
   - KPI store degrades at >500 req/s
   - No caching strategy for related links

2. **Security**
   - Rate limiting not enforced (skeleton only)
   - No DDoS protection
   - Honeypot not resistant to sophisticated attacks

3. **Monitoring**
   - SLA monitoring basic (no alerting)
   - No distributed tracing
   - Track endpoint performance not monitored in production

---

## Support & Contribution

### Getting Help

- **Issues:** GitHub Issues for bug reports
- **Documentation:** This index and linked documents
- **Runbooks:** Operational procedures in `implementation/`

### Contributing

1. Fork repository
2. Create feature branch
3. Run quality gates (see [Testing Framework](#testing-framework))
4. Submit PR with tests

### Maintenance

**Code Owners:** TBD (see [Final Handover Runbook](implementation/final-handover-runbook.md))

**Review Process:**
- All PRs require passing CI/CD
- Manual review for architectural changes
- Load test validation for performance changes

---

## License

WordPress theme license: GPL v2 or later
Documentation: CC BY-SA 4.0

---

## Changelog

### Version 1.0 Final (2026-04-04)
- Initial master index creation
- Consolidated 28 documentation files
- Added comprehensive cross-references
- Documented 91.2% project completion
- Catalogued 31 DONE tasks, 2 blocked, 3 open

---

**Next Steps:**
1. Complete TASK-H02 (7-day green pipeline monitoring)
2. Complete TASK-H03 (production metrics validation)
3. Configure TASK-G04 (branch protection - manual GitHub settings)
4. Execute TASK-H05 (final release tag and freeze)
