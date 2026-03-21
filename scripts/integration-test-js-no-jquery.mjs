#!/usr/bin/env node

/**
 * JS no-jQuery regression suite.
 *
 * Checks:
 * 1) Homepage response is 200 and does not inject jQuery runtime assets.
 * 2) Theme JS sources do not reference jQuery APIs ($, jQuery, .ready()).
 * 3) Enqueue contract does not list jquery as a dependency.
 *
 * Usage:
 *   node scripts/integration-test-js-no-jquery.mjs --base http://127.0.0.1:8080
 */

import fs from 'node:fs/promises';

const defaults = {
  base: 'http://127.0.0.1:8080',
  enqueueFile: 'poradnik.pro/inc/Enqueue.php',
  jsFiles: [
    'poradnik.pro/assets/js/core.js',
    'poradnik.pro/assets/js/tracking.js',
    'poradnik.pro/assets/js/attribution.js',
    'poradnik.pro/assets/js/search.js',
    'poradnik.pro/assets/js/leads.js',
    'poradnik.pro/assets/js/ui.js',
  ],
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

function assertNoMatch(source, regex, message) {
  if (regex.test(source)) {
    throw new Error(message);
  }
}

async function testHomepageHasNoJqueryAsset(base) {
  const url = `${base.replace(/\/$/, '')}/`;
  const response = await fetch(url);

  if (response.status !== 200) {
    throw new Error(`Homepage status ${response.status}, expected 200`);
  }

  const html = await response.text();

  assertNoMatch(
    html,
    /(wp-includes\/js\/jquery|jquery(?:\.min)?\.js|\/jquery\/?)/i,
    'Homepage includes jQuery asset reference'
  );

  console.log('✓ Homepage does not inject jQuery assets');
}

async function testThemeJsNoJqueryApi(jsFiles) {
  const forbidden = [
    { regex: /\bjQuery\s*\(/, message: 'Found jQuery(...) usage' },
    { regex: /\$\s*\(/, message: 'Found $(...) usage' },
    { regex: /\.ready\s*\(/, message: 'Found .ready(...) usage' },
    { regex: /\bjquery\b/i, message: 'Found jquery keyword usage' },
  ];

  for (const filePath of jsFiles) {
    const source = await fs.readFile(filePath, 'utf8');

    for (const rule of forbidden) {
      if (rule.regex.test(source)) {
        throw new Error(`${rule.message} in ${filePath}`);
      }
    }
  }

  console.log('✓ Theme JS files keep no-jQuery API contract');
}

async function testEnqueueNoJqueryDependency(enqueueFile) {
  const source = await fs.readFile(enqueueFile, 'utf8');

  assertNoMatch(source, /['\"]jquery['\"]/i, 'Enqueue contains jquery dependency');

  console.log('✓ Enqueue contract does not depend on jquery');
}

async function main() {
  const { base, enqueueFile, jsFiles } = parseArgs(process.argv.slice(2));

  console.log('JS no-jQuery regression suite');
  console.log(`base: ${base}`);
  console.log(`enqueue: ${enqueueFile}`);
  console.log(`files: ${jsFiles.length}`);
  console.log('');

  await testHomepageHasNoJqueryAsset(base);
  await testThemeJsNoJqueryApi(jsFiles);
  await testEnqueueNoJqueryDependency(enqueueFile);

  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
