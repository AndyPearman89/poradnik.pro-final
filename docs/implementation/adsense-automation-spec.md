# AdSense Placement Automation - Implementation Specification

## Overview

Automated AdSense placement system for Poradnik.pro to maximize RPM while maintaining UX quality.

**Status:** Planned (not yet implemented)
**Priority:** High (core monetization feature)
**Target Release:** Phase 2 (30-60 days post-MVP)

---

## Business Requirements

### Revenue Model
- **Target RPM:** 10-40 PLN
- **Target CTR:** 1-3%
- **Revenue Mix:** 25% of total revenue (AdSense + Affiliate + Lead = 100%)

### Placement Strategy

**Guide Pages (50% of content):**
- Placement 1: After intro (2 paragraphs)
- Placement 2: After each H2 section
- Placement 3: End of article
- **Density:** 25% ads, 50% content, 25% CTA/affiliate

**Ranking Pages (30% of content):**
- Placement: Between product cards only
- **Density:** 10% ads, 60% affiliate, 30% lead

**Local Pages (20% of content):**
- Placement: Sidebar or after intro only
- **Density:** 20% ads, 80% lead

---

## Technical Specification

### Service Architecture

**New Service:** `AdsenseService.php`

```php
<?php
declare(strict_types=1);

namespace PoradnikPro;

final class AdsenseService
{
    public static function insertAds(string $content, string $pageType): string;
    public static function getAdCode(string $placement): string;
    public static function shouldShowAds(string $pageType): bool;
    private static function config(): array;
    private static function injectAfterParagraph(string $content, int $paragraphIndex, string $adCode): string;
    private static function injectAfterHeading(string $content, string $headingLevel, int $index, string $adCode): string;
}
```

### Ad Placement Rules

**Guide Template (`templates/single-guide.php`):**
```php
// Current:
<div class="guide-content">
    <?php the_content(); ?>
</div>

// Proposed:
<div class="guide-content">
    <?php echo AdsenseService::insertAds(get_the_content(), 'guide'); ?>
</div>
```

**Ranking Template (`templates/single-ranking.php`):**
```php
// Insert between product cards #3 and #4
<?php if (count($top3) >= 3): ?>
    <?php echo AdsenseService::getAdCode('ranking-mid'); ?>
<?php endif; ?>
```

### Ad Code Configuration

**Option Key:** `poradnik_pro_adsense_config`

**Config Schema:**
```php
[
    'enabled' => true,
    'publisher_id' => 'ca-pub-XXXXXXXXXXXXXXXX',
    'guide_ad_slots' => [
        'intro' => ['slot' => '1234567890', 'format' => 'auto'],
        'h2' => ['slot' => '0987654321', 'format' => 'auto'],
        'end' => ['slot' => '1122334455', 'format' => 'auto'],
    ],
    'ranking_ad_slots' => [
        'mid' => ['slot' => '5544332211', 'format' => 'rectangle'],
    ],
    'local_ad_slots' => [
        'sidebar' => ['slot' => '6677889900', 'format' => 'vertical'],
    ],
    'density_limits' => [
        'guide' => 3, // max 3 ads per guide
        'ranking' => 1, // max 1 ad per ranking
        'local' => 1, // max 1 ad per local page
    ],
]
```

### Admin UI

**Admin Page:** `wp-admin/admin.php?page=poradnik-pro-adsense`

**Settings Fields:**
1. Enable/Disable toggle
2. Publisher ID input
3. Ad slot configuration per page type
4. Density limits (max ads per page)
5. Preview mode (shows ad placement without serving real ads)

### Content Parsing Algorithm

**Inject After Paragraph:**
```php
private static function injectAfterParagraph(string $content, int $paragraphIndex, string $adCode): string
{
    $dom = new DOMDocument();
    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $paragraphs = $dom->getElementsByTagName('p');
    if ($paragraphs->length <= $paragraphIndex) {
        return $content;
    }

    $targetP = $paragraphs->item($paragraphIndex);
    $adNode = $dom->createDocumentFragment();
    $adNode->appendXML('<div class="poradnik-ad">' . $adCode . '</div>');

    $targetP->parentNode->insertBefore($adNode, $targetP->nextSibling);

    return $dom->saveHTML();
}
```

**Inject After Heading:**
```php
private static function injectAfterHeading(string $content, string $headingLevel, int $index, string $adCode): string
{
    $dom = new DOMDocument();
    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $headings = $dom->getElementsByTagName($headingLevel); // 'h2', 'h3', etc.
    if ($headings->length <= $index) {
        return $content;
    }

    $targetH = $headings->item($index);
    $adNode = $dom->createDocumentFragment();
    $adNode->appendXML('<div class="poradnik-ad">' . $adCode . '</div>');

    // Find next sibling that is not a heading (to avoid breaking heading hierarchy)
    $nextSibling = $targetH->nextSibling;
    while ($nextSibling && $nextSibling->nodeType !== XML_ELEMENT_NODE) {
        $nextSibling = $nextSibling->nextSibling;
    }

    $targetH->parentNode->insertBefore($adNode, $nextSibling);

    return $dom->saveHTML();
}
```

---

## Implementation Plan

### Phase 1: Foundation (Week 1)

**Tasks:**
- [ ] Create `AdsenseService.php` skeleton
- [ ] Add admin menu page registration
- [ ] Implement config storage (options table)
- [ ] Create admin UI for ad slot configuration

**Acceptance Criteria:**
- Admin can save/load AdSense config
- Config is persisted in `poradnik_pro_adsense_config` option

### Phase 2: Content Injection (Week 2)

**Tasks:**
- [ ] Implement DOM parsing for paragraph injection
- [ ] Implement DOM parsing for heading injection
- [ ] Add density limit enforcement
- [ ] Update `single-guide.php` to use `AdsenseService::insertAds()`
- [ ] Update `single-ranking.php` to inject mid-content ads
- [ ] Update `single-local.php` for sidebar ads

**Acceptance Criteria:**
- Ads appear after 2nd paragraph on guide pages
- Ads appear after each H2 on guide pages
- Ads appear at end of guide pages
- Max ads per page type enforced
- No ads on ranking pages with <3 products

### Phase 3: Testing & Validation (Week 3)

**Tasks:**
- [ ] Create unit test: `scripts/unit-test-adsense-service.php`
- [ ] Create integration test: `scripts/integration-test-adsense-placement.mjs`
- [ ] Test ad density limits
- [ ] Test preview mode (placeholder ads)
- [ ] Visual regression test (mobile/desktop)

**Acceptance Criteria:**
- All tests pass
- Ad placement matches wireframes
- Mobile UX not degraded (Lighthouse score maintained)
- CTA visibility not reduced by >10%

### Phase 4: Production Rollout (Week 4)

**Tasks:**
- [ ] Enable on 10% of traffic (feature flag)
- [ ] Monitor RPM, CTR, CWV metrics
- [ ] A/B test: ads vs no-ads
- [ ] Gradual rollout to 100%

**Acceptance Criteria:**
- RPM within 10-40 PLN target
- CTR within 1-3% target
- CWV (LCP, CLS, FID) not degraded
- Conversion rate (lead/affiliate) delta <5%

---

## Testing Strategy

### Unit Tests

**File:** `scripts/unit-test-adsense-service.php`

**Test Cases:**
1. Config retrieval with defaults
2. Paragraph injection (2nd paragraph)
3. Heading injection (after H2)
4. Density limit enforcement
5. Page type filtering (guide vs ranking vs local)

### Integration Tests

**File:** `scripts/integration-test-adsense-placement.mjs`

**Test Cases:**
1. Guide page: 3 ads visible (intro, h2, end)
2. Ranking page: 1 ad visible (mid-content)
3. Local page: 1 ad visible (sidebar)
4. Mobile: sticky ad not overlapping CTA
5. Preview mode: placeholder ads rendered

### Performance Tests

**Criteria:**
- LCP delta: <100ms
- CLS delta: <0.05
- FID delta: <50ms

### A/B Test Setup

**Experiment:** `adsense_rollout`
**Variants:**
- A (control): No ads
- B (treatment): Ads enabled

**Metrics:**
- Primary: RPM
- Secondary: CTR, conversion rate (lead + affiliate), bounce rate, time on page

---

## UX Considerations

### Mobile Optimization

**Requirements:**
- Ad width: 100% (responsive)
- Min height: 250px (to prevent layout shift)
- No sticky ads overlapping CTA buttons
- Lazy loading below fold

### Accessibility

**Requirements:**
- Ad containers have `aria-label="Reklama"` for screen readers
- Keyboard navigation not blocked
- Focus not trapped in ad iframes

### Core Web Vitals

**Requirements:**
- Reserve space for ads (prevent CLS)
- Async ad loading (prevent LCP degradation)
- Defer ad scripts until page interactive

---

## Rollback Plan

**Trigger Conditions:**
- RPM drops below 5 PLN (50% of target)
- CWV degraded (LCP >2.5s, CLS >0.1, FID >100ms)
- Conversion rate drops >10%

**Rollback Procedure:**
1. Disable via admin toggle: `poradnik_pro_adsense_config['enabled'] = false`
2. Clear cache (if object cache enabled)
3. Monitor metrics for 24h
4. Root cause analysis

---

## Monitoring & Alerts

### KPIs to Track

**Dashboard:** `wp-admin/admin.php?page=poradnik-pro-kpi`

**Metrics:**
- RPM (revenue per 1000 impressions)
- CTR (click-through rate)
- Ad impressions per page
- Revenue attribution (AdSense vs affiliate vs lead)

### Alerts

**Thresholds:**
- RPM <5 PLN → Warning
- CTR <0.5% → Warning
- Ad impressions per page >5 → Warning (over-saturation)

---

## Known Limitations

1. **Requires Google AdSense Account:**
   - Must have approved AdSense publisher ID
   - Ad slots must be created in AdSense console

2. **DOM Manipulation Overhead:**
   - Content parsing adds ~10-20ms per page load
   - Mitigated by caching parsed content (transient cache)

3. **Ad Blocker Compatibility:**
   - ~25-40% of users may have ad blockers
   - Fallback: Show affiliate CTA in ad slots

4. **GDPR/Cookie Consent:**
   - Not handled by this implementation
   - Requires separate consent management platform (CMP)

---

## Future Enhancements

1. **Auto-optimization:**
   - ML model to predict best ad density per page type
   - Dynamic adjustment based on user engagement

2. **Fallback Monetization:**
   - If user has ad blocker, show affiliate CTA in ad slots

3. **Header Bidding:**
   - Integrate prebid.js for programmatic ad auction
   - Increase RPM by 20-40%

4. **Lazy Loading:**
   - Only load ads when scrolled into viewport
   - Reduce initial page weight

---

## Appendix

### Ad Code Example

**AdSense Auto Ads:**
```html
<div class="poradnik-ad" aria-label="Reklama">
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="ca-pub-XXXXXXXXXXXXXXXX"
         data-ad-slot="1234567890"
         data-ad-format="auto"
         data-full-width-responsive="true"></ins>
    <script>
         (adsbygoogle = window.adsbygoogle || []).push({});
    </script>
</div>
```

### Wireframes

**Guide Page with Ads:**
```
[Intro paragraph 1]
[Intro paragraph 2]
[AD PLACEMENT 1]  <-- After 2nd paragraph
[Section 1: H2]
[Content]
[AD PLACEMENT 2]  <-- After H2
[Section 2: H2]
[Content]
[AD PLACEMENT 3]  <-- After H2
[Conclusion]
[AD PLACEMENT 4]  <-- End of article
[CTA Block]
```

**Ranking Page with Ads:**
```
[Intro]
[Product Card 1]
[Product Card 2]
[Product Card 3]
[AD PLACEMENT]  <-- Mid-content (between card 3 and 4)
[Product Card 4]
[Product Card 5]
[Comparison Table]
[CTA Block]
```

---

## References

- [Google AdSense Best Practices](https://support.google.com/adsense/answer/6002621)
- [AdSense Ad Formats](https://support.google.com/adsense/answer/9183460)
- [Core Web Vitals](https://web.dev/vitals/)
- WordPress Coding Standards: [PHP](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
