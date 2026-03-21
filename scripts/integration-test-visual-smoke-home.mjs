#!/usr/bin/env node

/**
 * Visual smoke contract for homepage hero/sections/CTA.
 *
 * Checks:
 * 1) Home page returns HTTP 200.
 * 2) Runtime error markers are not present in HTML.
 * 3) Hero/search contract markers are present.
 * 4) Section headings and lead form anchor contract are present.
 * 5) CTA block and sticky CTA contract are present.
 * 6) Theme stylesheet reference is present.
 *
 * Usage:
 *   node scripts/integration-test-visual-smoke-home.mjs --base http://127.0.0.1:8080
 */

const defaults = {
  base: 'http://127.0.0.1:8080',
};

const runtimeErrorPatterns = [
  /fatal error/i,
  /parse error/i,
  /uncaught\s+error/i,
  /there has been a critical error on this website/i,
  /\bwarning\b\s*:/i,
  /\bnotice\b\s*:/i,
  /\bdeprecated\b\s*:/i,
  /call to undefined function/i,
  /stack trace/i,
];

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i += 1) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--base' && val) {
      out.base = val;
      i += 1;
    }
  }

  return out;
}

function assertIncludes(source, needle, message) {
  if (!source.includes(needle)) {
    throw new Error(message);
  }
}

function assertRegex(source, regex, message) {
  if (!regex.test(source)) {
    throw new Error(message);
  }
}

function assertNoRuntimeErrorMarkers(html) {
  const hits = runtimeErrorPatterns
    .filter((pattern) => pattern.test(html))
    .map((pattern) => pattern.toString());

  if (hits.length > 0) {
    throw new Error(`Homepage contains runtime error markers: ${hits.join(', ')}`);
  }
}

async function main() {
  const { base } = parseArgs(process.argv.slice(2));
  const homepageUrl = `${base.replace(/\/$/, '')}/`;

  console.log('Visual smoke test (hero/sections/CTA)');
  console.log(`base: ${base}`);
  console.log('');

  const response = await fetch(homepageUrl);

  if (response.status !== 200) {
    throw new Error(`Home page returned ${response.status}, expected 200`);
  }

  const html = await response.text();

  assertNoRuntimeErrorMarkers(html);
  console.log('✓ Homepage has no runtime error markers');

  assertIncludes(html, 'class="pp-hero"', 'Missing hero section marker: .pp-hero');
  assertIncludes(html, 'data-pp-search', 'Missing hero search marker: data-pp-search');
  assertIncludes(html, 'data-pp-search-results', 'Missing search results marker: data-pp-search-results');
  assertIncludes(html, '#lead-form', 'Missing lead-form anchor reference');
  console.log('✓ Hero and search contract markers are present');

  assertIncludes(html, 'Popularne poradniki', 'Missing section heading: Popularne poradniki');
  assertIncludes(html, 'Rankingi i porownania', 'Missing section heading: Rankingi i porownania');
  assertIncludes(html, 'Zapytaj specjaliste', 'Missing section heading: Zapytaj specjaliste');
  console.log('✓ Main homepage sections are present');

  assertIncludes(html, 'class="pp-cta"', 'Missing CTA block marker: .pp-cta');
  assertIncludes(html, 'Przejdz do formularza', 'Missing CTA button label: Przejdz do formularza');
  assertIncludes(html, 'data-pp-sticky-cta', 'Missing sticky CTA marker: data-pp-sticky-cta');
  assertIncludes(html, 'Skontaktuj sie teraz', 'Missing sticky CTA label');
  console.log('✓ CTA block and sticky CTA markers are present');

  assertRegex(
    html,
    /wp-content\/themes\/poradnik\.pro\/style\.css(?:\?ver=[^"']+)?/i,
    'Missing theme stylesheet reference (style.css)'
  );
  console.log('✓ Theme stylesheet reference is present');

  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
