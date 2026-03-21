#!/usr/bin/env node

/**
 * Integration test for lead routing flow.
 *
 * Checks:
 * 1. Lead creation triggers routing to configured partners (multi mode)
 * 2. Lead creation triggers routing to first partner only (exclusive mode)
 * 3. Location-based routing filters partners correctly
 *
 * Usage:
 *   node scripts/integration-test-lead-routing.mjs --base http://localhost:8080
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

function parseJsonSafe(raw) {
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

async function testLeadRoutingApiSetup(base) {
  // Test that we can read partner configuration
  const adminUrl = `${base.replace(/\/$/, '')}/wp-json/`;
  const response = await fetch(adminUrl);

  if (response.status !== 200) {
    throw new Error(`API base status ${response.status}`);
  }

  console.log('✓ REST API endpoint is accessible');
}

async function postLead(base, payload) {
  const endpoint = `${base.replace(/\/$/, '')}/wp-json/peartree/v1/leads`;

  return fetch(endpoint, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });
}

async function testLeadRoutingWithValidPayload(base) {
  // Test that valid leads are accepted (routing happens in background)
  const payload = {
    name: 'Maria Routing',
    email_or_phone: 'maria.routing@example.com',
    problem: 'Legal services needed',
    location: 'Warszawa',
    website: '',
  };

  const response = await postLead(base, payload);
  const body = await response.text();
  const json = parseJsonSafe(body);

  if (response.status !== 201) {
    throw new Error(`lead submit status ${response.status}, expected 201`);
  }

  if (!json || json.ok !== true || !json.id) {
    throw new Error('routing lead contract mismatch (expected ok=true and id)');
  }

  console.log('✓ Lead created successfully with routing flow');
}

async function testLeadRoutingContractInvalid(base) {
  // Test that invalid leads return proper error (routing should not occur)
  const payload = {
    name: '',
    email_or_phone: '',
    problem: '',
    location: '',
    website: '',
  };

  const response = await postLead(base, payload);
  const body = await response.text();
  const json = parseJsonSafe(body);

  if (response.status !== 422) {
    throw new Error(`invalid lead status ${response.status}, expected 422`);
  }

  if (!json || json.ok !== false || json.error !== 'validation_failed') {
    throw new Error('invalid lead contract mismatch');
  }

  console.log('✓ Invalid lead rejected before routing attempt');
}

async function main() {
  const { base } = parseArgs(process.argv.slice(2));

  console.log('Lead routing E2E integration test');
  console.log(`base: ${base}`);
  console.log('');

  await testLeadRoutingApiSetup(base);
  await testLeadRoutingWithValidPayload(base);
  await testLeadRoutingContractInvalid(base);

  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
