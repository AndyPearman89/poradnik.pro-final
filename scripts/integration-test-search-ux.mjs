#!/usr/bin/env node

/**
 * Search UX interaction contract test (intent mapping + debounce + empty state).
 *
 * Checks:
 * 1) Homepage exposes search widget markers.
 * 2) search.js enforces debounce for input events.
 * 3) search.js clears empty/short query state.
 * 4) search.js keeps deterministic intent routing order (high/mid/low).
 *
 * Usage:
 *   node scripts/integration-test-search-ux.mjs --base http://127.0.0.1:8080
 */

import fs from 'node:fs/promises';

const defaults = {
  base: 'http://127.0.0.1:8080',
  file: 'poradnik.pro/assets/js/search.js',
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

    if (key === '--file' && val) {
      out.file = val;
      i += 1;
    }
  }

  return out;
}

function assertMatch(source, regex, message) {
  if (!regex.test(source)) {
    throw new Error(message);
  }
}

async function testHomepageMarkers(base) {
  const url = `${base.replace(/\/$/, '')}/`;
  const response = await fetch(url);

  if (response.status !== 200) {
    throw new Error(`Homepage status ${response.status}, expected 200`);
  }

  const html = await response.text();
  const required = ['data-pp-search', 'data-pp-search-results', 'name="q"'];
  const missing = required.filter((marker) => !html.includes(marker));

  if (missing.length > 0) {
    throw new Error(`Missing homepage search markers: ${missing.join(', ')}`);
  }

  console.log('✓ Homepage exposes search widget markers');
}

async function testSearchScriptContracts(filePath) {
  const source = await fs.readFile(filePath, 'utf8');

  // Debounce contract: input clears timer and re-schedules search in 250ms.
  assertMatch(source, /clearTimeout\(timer\)/, 'Missing debounce clearTimeout(timer)');
  assertMatch(source, /setTimeout\(runSearch,\s*250\)/, 'Missing debounce schedule setTimeout(runSearch, 250)');

  // Empty-state contract: short query should clear results and return early.
  assertMatch(source, /query\.length\s*<\s*2/, 'Missing short-query guard query.length < 2');
  assertMatch(source, /output\.innerHTML\s*=\s*''\s*;/, 'Missing empty-state reset output.innerHTML = \"\"');

  // Intent mapping route order contracts.
  assertMatch(
    source,
    /intent\s*===\s*'high'[\s\S]*renderGroup\('Specjalisci'[\s\S]*renderGroup\('Rankingi'[\s\S]*renderGroup\('Poradniki'/,
    'Missing high-intent route order: Specjalisci -> Rankingi -> Poradniki'
  );
  assertMatch(
    source,
    /intent\s*===\s*'mid'[\s\S]*renderGroup\('Rankingi'[\s\S]*renderGroup\('Poradniki'[\s\S]*renderGroup\('Specjalisci'/,
    'Missing mid-intent route order: Rankingi -> Poradniki -> Specjalisci'
  );
  assertMatch(
    source,
    /:\s*\[[\s\S]*renderGroup\('Poradniki'[\s\S]*renderGroup\('Rankingi'[\s\S]*renderGroup\('Specjalisci'/,
    'Missing low-intent route order: Poradniki -> Rankingi -> Specjalisci'
  );

  console.log('✓ Search JS keeps debounce contract');
  console.log('✓ Search JS keeps empty-state contract');
  console.log('✓ Search JS keeps intent mapping route order contract');
}

async function main() {
  const { base, file } = parseArgs(process.argv.slice(2));

  console.log('Search UX interaction integration test');
  console.log(`base: ${base}`);
  console.log(`file: ${file}`);
  console.log('');

  await testHomepageMarkers(base);
  await testSearchScriptContracts(file);

  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
