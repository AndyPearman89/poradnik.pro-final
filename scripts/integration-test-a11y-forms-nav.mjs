#!/usr/bin/env node

/**
 * A11y audit for forms/navigation landmarks on homepage.
 *
 * Checks:
 * 1) Home page responds 200.
 * 2) Skip link to #main exists.
 * 3) Navigation landmark with aria-label exists.
 * 4) Search form has associated label and aria-live results region.
 * 5) Lead form has accessible label contract and submit/status semantics.
 *
 * Usage:
 *   node scripts/integration-test-a11y-forms-nav.mjs --base http://127.0.0.1:8080
 */

const defaults = {
  base: 'http://127.0.0.1:8080',
};

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

function assertRegex(source, regex, message) {
  if (!regex.test(source)) {
    throw new Error(message);
  }
}

async function main() {
  const { base } = parseArgs(process.argv.slice(2));
  const homepageUrl = `${base.replace(/\/$/, '')}/`;

  console.log('A11y audit (forms/navigation)');
  console.log(`base: ${base}`);
  console.log('');

  const response = await fetch(homepageUrl);

  if (response.status !== 200) {
    throw new Error(`Home page returned ${response.status}, expected 200`);
  }

  const html = await response.text();

  assertRegex(html, /<a[^>]*class="[^"]*pp-skip-link[^"]*"[^>]*href="#main"/i, 'Missing skip link to #main');
  console.log('✓ Skip link to #main is present');

  assertRegex(html, /<nav[^>]*aria-label="[^"]+"/i, 'Missing navigation landmark with aria-label');
  console.log('✓ Navigation landmark has aria-label');

  assertRegex(html, /<label[^>]*for="pp-search-input"/i, 'Missing label for search input #pp-search-input');
  assertRegex(html, /<div[^>]*data-pp-search-results[^>]*aria-live="polite"/i, 'Missing aria-live polite on search results region');
  console.log('✓ Search form accessibility contract is present');

  assertRegex(html, /<form[^>]*data-pp-lead-form[^>]*aria-label="[^"]+"/i, 'Missing aria-label on lead form');
  assertRegex(html, /<label[^>]*for="lead-name"/i, 'Missing label for lead-name');
  assertRegex(html, /<label[^>]*for="lead-contact"/i, 'Missing label for lead-contact');
  assertRegex(html, /<label[^>]*for="lead-problem"/i, 'Missing label for lead-problem');
  assertRegex(html, /<label[^>]*for="lead-location"/i, 'Missing label for lead-location');
  assertRegex(html, /<button[^>]*type="submit"/i, 'Missing submit button on lead form');
  assertRegex(html, /<p[^>]*data-pp-form-status[^>]*aria-live="polite"/i, 'Missing aria-live polite status for lead form');
  console.log('✓ Lead form accessibility contract is present');

  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
