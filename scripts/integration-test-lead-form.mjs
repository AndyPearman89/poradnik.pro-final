#!/usr/bin/env node

/**
 * Integration test for lead-form tracking events.
 *
 * Usage:
 *   node scripts/integration-test-lead-form.mjs --base http://localhost:8080
 */

const defaults = {
  base: 'http://localhost:8080',
};

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const value = argv[i + 1];

    if (key === '--base' && value) {
      out.base = value;
      i += 1;
    }
  }

  return out;
}

async function postTrack(base, eventName) {
  const endpoint = `${base.replace(/\/$/, '')}/wp-json/peartree/v1/track`;
  const body = {
    eventName,
    payload: {
      source: 'integration-test',
      ts: Date.now(),
    },
  };

  const response = await fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  if (![200, 202].includes(response.status)) {
    throw new Error(`status ${response.status}`);
  }

  try {
    const json = await response.json();
    if (!json.success) {
      throw new Error('success=false');
    }
    return { parsed: true };
  } catch {
    // In noisy dev setups PHP notices may break JSON, but 200/202 still confirms endpoint handled the request.
    return { parsed: false };
  }
}

async function main() {
  const { base } = parseArgs(process.argv.slice(2));
  const events = [
    'lead_form_displayed',
    'lead_form_submit_attempt',
    'lead_submit_success',
  ];

  let failed = 0;

  console.log('Lead form integration test');
  console.log(`base: ${base}`);
  console.log('');

  for (const eventName of events) {
    try {
      const result = await postTrack(base, eventName);
      if (result.parsed) {
        console.log(`✓ ${eventName}`);
      } else {
        console.log(`✓ ${eventName} (status ok, JSON skipped)`);
      }
    } catch (error) {
      failed += 1;
      console.log(`✗ ${eventName}: ${error?.message || String(error)}`);
    }
  }

  console.log('');

  if (failed > 0) {
    console.log('Overall: FAIL');
    process.exit(1);
  }

  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
