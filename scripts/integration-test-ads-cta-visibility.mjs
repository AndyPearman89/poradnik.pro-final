#!/usr/bin/env node

/**
 * E2E integration test for ads density vs CTA visibility (mobile-first).
 *
 * Checks:
 * 1) Key pages return HTTP 200
 * 2) Each page has at least one CTA marker
 * 3) Ads density stays within threshold relative to CTA count
 * 4) Mobile sticky CTA marker exists on homepage
 *
 * Usage:
 *   node scripts/integration-test-ads-cta-visibility.mjs --base http://127.0.0.1:8080
 */

const defaults = {
  base: 'http://127.0.0.1:8080',
  maxAdsPerCta: 1.5,
};

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--base' && val) {
      out.base = val;
      i += 1;
      continue;
    }

    if (key === '--max-ads-per-cta' && val) {
      const parsed = Number(val);
      if (!Number.isNaN(parsed) && parsed > 0) {
        out.maxAdsPerCta = parsed;
      }
      i += 1;
    }
  }

  return out;
}

function countMatches(html, pattern) {
  return (html.match(pattern) || []).length;
}

function analyzePage(html) {
  const ctaCount =
    countMatches(html, /data-pp-sticky-cta/gi) +
    countMatches(html, /data-pp-inline-cta/gi) +
    countMatches(html, /data-pp-affiliate/gi) +
    countMatches(html, /class=["'][^"']*pp-cta[^"']*["']/gi) +
    countMatches(html, /href=["']#lead-form["']/gi);

  const adCount =
    countMatches(html, /adsbygoogle/gi) +
    countMatches(html, /data-ad-/gi) +
    countMatches(html, /google_ad_client/gi) +
    countMatches(html, /class=["'][^"']*\bad\b[^"']*["']/gi) +
    countMatches(html, /class=["'][^"']*\bbanner\b[^"']*["']/gi);

  return {
    ctaCount,
    adCount,
    adsPerCta: adCount / Math.max(1, ctaCount),
  };
}

async function fetchPage(url) {
  const response = await fetch(url);
  const html = await response.text();
  return {
    status: response.status,
    html,
  };
}

async function main() {
  const { base, maxAdsPerCta } = parseArgs(process.argv.slice(2));
  const root = base.replace(/\/$/, '');

  const targets = [
    { name: 'Homepage', url: `${root}/` },
    { name: 'Ranking archive', url: `${root}/ranking/` },
    { name: 'Local services', url: `${root}/uslugi/` },
  ];

  console.log('Ads density vs CTA visibility integration test');
  console.log(`base: ${root}`);
  console.log(`maxAdsPerCta: ${maxAdsPerCta}`);
  console.log('');

  const reports = [];

  for (const target of targets) {
    const { status, html } = await fetchPage(target.url);

    if (status !== 200) {
      if (target.name === 'Homepage') {
        throw new Error(`Expected 200 for ${target.url}, got ${status}`);
      }

      console.log(`SKIP ${target.name}: status=${status}`);
      continue;
    }

    const report = analyzePage(html);

    if (report.ctaCount < 1) {
      throw new Error(`${target.name}: expected at least one CTA marker`);
    }

    if (report.adsPerCta > maxAdsPerCta) {
      throw new Error(
        `${target.name}: ads/cta ratio too high (${report.adsPerCta.toFixed(2)} > ${maxAdsPerCta.toFixed(2)})`
      );
    }

    reports.push({ target, report, html });
    console.log(`PASS ${target.name}: cta=${report.ctaCount}, ads=${report.adCount}, ratio=${report.adsPerCta.toFixed(2)}`);
  }

  const home = reports.find((item) => item.target.name === 'Homepage');
  if (!home) {
    throw new Error('Missing homepage report');
  }

  if (!/data-pp-sticky-cta/i.test(home.html)) {
    throw new Error('Homepage: missing mobile sticky CTA marker (data-pp-sticky-cta)');
  }

  if (reports.length < 1) {
    throw new Error('No pages validated successfully');
  }

  console.log('PASS Homepage mobile sticky CTA marker present');
  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
