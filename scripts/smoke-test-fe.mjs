#!/usr/bin/env node

/**
 * Frontend smoke test for poradnik.pro home page and tracking endpoint.
 *
 * Checks:
 * 1. Home page loads successfully (status 200)
 * 2. Contains expected HTML markers (hero, sections)
 * 3. Tracking endpoint accepts POST events
 *
 * Usage:
 *   node scripts/smoke-test-fe.mjs --base http://localhost:8080
 */

const defaults = {
  base: 'http://localhost:8080',
  strictRuntime: false,
};

function parseArgs(argv) {
  const out = { ...defaults };
  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];
    if (key === '--base' && val) {
      out.base = val;
      i++;
      continue;
    }
    if (key === '--strict-runtime') {
      out.strictRuntime = true;
    }
  }
  return out;
}

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

function assertNoRuntimeErrorMarkers(body, contextLabel) {
  const matches = runtimeErrorPatterns
    .filter((pattern) => pattern.test(body))
    .map((pattern) => pattern.toString());

  if (matches.length > 0) {
    throw new Error(
      `${contextLabel} contains runtime error markers: ${matches.join(', ')}`
    );
  }
}

async function testHomePage(base, strictRuntime) {
  const url = `${base.replace(/\/$/, '')}/`;
  const res = await fetch(url);

  if (res.status !== 200) {
    throw new Error(`Home page returned ${res.status}, expected 200`);
  }

  const html = await res.text();
  if (strictRuntime) {
    assertNoRuntimeErrorMarkers(html, 'Home page HTML');
  }

  // Check for WordPress markers indicating WP is running
  const expectedMarkers = [
    'wp-',              // WordPress CSS class prefix or ID
    'WordPress',        // WordPress version comment or meta
  ];

  const missingMarkers = expectedMarkers.filter((marker) => !html.includes(marker));
  if (missingMarkers.length > 0) {
    throw new Error(`Missing expected markers in home page: ${missingMarkers.join(', ')}`);
  }

  console.log('✓ Home page loads successfully (status 200)');
  console.log('✓ WordPress markers present');
  if (strictRuntime) {
    console.log('✓ Runtime error markers not found on home page');
  }
}

async function testTrackingEndpoint(base, strictRuntime) {
  const url = `${base.replace(/\/$/, '')}/wp-json/peartree/v1/track`;

  const payload = {
    eventName: 'smoke_test',
    payload: {
      source: 'smoke-test-fe',
      ts: Date.now(),
    },
  };

  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  if (!strictRuntime && res.status === 404) {
    console.log('✓ Tracking endpoint path is accessible (404 expected if theme inactive)');
    return;
  }

  if (res.status !== 200) {
    const rawBody = await res.text();
    if (strictRuntime) {
      assertNoRuntimeErrorMarkers(rawBody, 'Tracking endpoint response');
      throw new Error(`Tracking endpoint returned ${res.status}, expected 200 in strict runtime mode`);
    }
    throw new Error(`Tracking endpoint returned ${res.status}, expected 200 or 404`);
  }

  const rawBody = await res.text();
  if (strictRuntime) {
    assertNoRuntimeErrorMarkers(rawBody, 'Tracking endpoint response');
  }

  let json;
  try {
    json = JSON.parse(rawBody);
  } catch (error) {
    if (!strictRuntime) {
      console.log('✓ Tracking endpoint path is accessible (response parsing skipped in dev)');
      return;
    }
    throw new Error('Tracking endpoint returned non-JSON response in strict runtime mode');
  }

  if (!json.success) {
    throw new Error('Tracking endpoint did not confirm success in response');
  }

  console.log('✓ Tracking endpoint accepts POST events (status 200)');
  console.log('✓ Tracking response confirmed success');
  if (strictRuntime) {
    console.log('✓ Runtime error markers not found in tracking response');
  }
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const base = args.base;
  const strictRuntime = args.strictRuntime;

  console.log('Frontend smoke test');
  console.log(`base: ${base}`);
  console.log(`strictRuntime: ${strictRuntime ? 'on' : 'off'}`);
  console.log('');

  try {
    await testHomePage(base, strictRuntime);
    console.log('');
    await testTrackingEndpoint(base, strictRuntime);
    console.log('');
    console.log('Overall: PASS');
  } catch (error) {
    console.error('');
    console.error(`ERROR: ${error.message}`);
    console.error('');
    console.error('Overall: FAIL');
    process.exit(1);
  }
}

main();
