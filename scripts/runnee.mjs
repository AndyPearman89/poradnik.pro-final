#!/usr/bin/env node

/**
 * runnee: autonomous entrypoint for /track load testing.
 *
 * It tries to detect an available local WP base URL and then runs
 * scripts/run-track-load-suite.mjs with that base.
 *
 * Usage:
 *   node scripts/runnee.mjs
 *   node scripts/runnee.mjs --base http://localhost:8080
 */

import { spawnSync } from 'node:child_process';

const DEFAULT_CANDIDATES = [
  'http://127.0.0.1:8080',
  'http://localhost:8080',
  'http://127.0.0.1:2000',
  'http://localhost:2000',
  'http://127.0.0.1',
  'http://localhost',
];

function parseArgs(argv) {
  const out = { base: '' };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--base' && val) {
      out.base = val;
      i++;
    }
  }

  return out;
}

async function isReachable(base, maxRetries = 3, delayMs = 300) {
  const url = `${base.replace(/\/$/, '')}/wp-json/`;

  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      const res = await fetch(url, { method: 'GET' });
      if (res.ok) return true;
    } catch {
      // network error — will retry
    }

    if (attempt < maxRetries) {
      await new Promise((r) => setTimeout(r, delayMs * attempt));
    }
  }

  return false;
}

async function pickBase(manualBase) {
  if (manualBase) {
    return (await isReachable(manualBase)) ? manualBase : '';
  }

  for (const candidate of DEFAULT_CANDIDATES) {
    // Try a few typical local ports before failing.
    if (await isReachable(candidate)) {
      return candidate;
    }
  }

  return '';
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const picked = await pickBase(args.base);

  if (!picked) {
    console.error('runnee: no reachable local WordPress base URL found.');
    console.error('runnee: start WP and retry, or pass --base <url>.');
    process.exit(2);
  }

  console.log(`runnee: using base ${picked}`);

  const res = spawnSync(
    'node',
    ['scripts/run-track-load-suite.mjs', '--base', picked],
    { stdio: 'inherit' }
  );

  process.exit(res.status ?? 1);
}

main().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
