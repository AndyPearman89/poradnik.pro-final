#!/usr/bin/env node

/**
 * End-to-end integration test for lead flow.
 *
 * Checks:
 * 1. Home page contains lead form marker
 * 2. Valid lead payload is accepted by /peartree/v1/leads
 * 3. Duplicate lead payload follows accepted duplicate contract
 * 4. Invalid payload is rejected with validation contract
 * 5. Honeypot payload is accepted without hard failure
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

function parseJsonSafe(raw) {
  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

async function testHomepageLeadForm(base) {
  const url = `${base.replace(/\/$/, '')}/`;
  const response = await fetch(url);

  if (response.status !== 200) {
    throw new Error(`home page status ${response.status}`);
  }

  const html = await response.text();
  const requiredMarkers = [
    'data-pp-lead-form',
    'name="name"',
    'name="email_or_phone"',
    'name="problem"',
    'name="location"',
    'name="website"',
  ];

  const missing = requiredMarkers.filter((marker) => !html.includes(marker));
  if (missing.length > 0) {
    throw new Error(`missing lead form markers: ${missing.join(', ')}`);
  }

  console.log('✓ Home page exposes lead form and required fields');
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

async function testLeadFlow(base) {
  const validPayload = {
    name: 'Jan E2E',
    email_or_phone: 'jan.e2e@example.com',
    problem: 'Potrzebuje szybkiej porady prawnej',
    location: 'Warszawa',
    website: '',
  };

  const validResponse = await postLead(base, validPayload);
  const validRaw = await validResponse.text();
  const validJson = parseJsonSafe(validRaw);

  if (![201, 202].includes(validResponse.status)) {
    throw new Error(`valid lead status ${validResponse.status}`);
  }

  if (!validJson || validJson.ok !== true) {
    throw new Error('valid lead payload did not return ok=true JSON');
  }

  console.log('✓ Valid lead payload accepted by /peartree/v1/leads');

  const duplicateResponse = await postLead(base, validPayload);
  const duplicateRaw = await duplicateResponse.text();
  const duplicateJson = parseJsonSafe(duplicateRaw);

  if (duplicateResponse.status !== 202) {
    throw new Error(`duplicate lead status ${duplicateResponse.status}`);
  }

  if (!duplicateJson || duplicateJson.ok !== true || duplicateJson.duplicate !== true) {
    throw new Error('duplicate lead contract mismatch (expected ok=true and duplicate=true)');
  }

  console.log('✓ Duplicate lead payload follows dedup contract');

  const invalidResponse = await postLead(base, {
    name: '',
    email_or_phone: '',
    problem: '',
    location: '',
    website: '',
  });
  const invalidRaw = await invalidResponse.text();
  const invalidJson = parseJsonSafe(invalidRaw);

  if (invalidResponse.status !== 422) {
    throw new Error(`invalid lead status ${invalidResponse.status}`);
  }

  if (!invalidJson || invalidJson.ok !== false || invalidJson.error !== 'validation_failed') {
    throw new Error('invalid lead contract mismatch (expected ok=false, error=validation_failed)');
  }

  console.log('✓ Invalid lead payload is rejected with validation contract');

  const honeypotResponse = await postLead(base, {
    ...validPayload,
    website: 'bot-value',
  });
  const honeypotRaw = await honeypotResponse.text();
  const honeypotJson = parseJsonSafe(honeypotRaw);

  if (honeypotResponse.status !== 202) {
    throw new Error(`honeypot lead status ${honeypotResponse.status}`);
  }

  if (!honeypotJson || honeypotJson.ok !== true) {
    throw new Error('honeypot payload should return ok=true');
  }

  console.log('✓ Honeypot payload is accepted with safe response');
}

async function main() {
  const { base } = parseArgs(process.argv.slice(2));

  console.log('Lead flow E2E integration test');
  console.log(`base: ${base}`);
  console.log('');

  await testHomepageLeadForm(base);
  console.log('');
  await testLeadFlow(base);
  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
