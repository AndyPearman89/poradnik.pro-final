#!/usr/bin/env node

/**
 * E2E HTTP test for KPI dashboard top_sources tie-order contract.
 *
 * Flow:
 * 1. Login to wp-admin using admin credentials
 * 2. Open KPI dashboard page
 * 3. Assert dashboard markers and deterministic tie-order alpha -> beta
 *
 * Usage:
 *   node scripts/integration-test-kpi-dashboard.mjs --base http://localhost:8080 --admin-user admin --admin-password admin123!
 */

const defaults = {
  base: 'http://localhost:8080',
  adminUser: 'admin',
  adminPassword: 'admin123!',
};

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const value = argv[i + 1];

    if (key === '--base' && value) {
      out.base = value;
      i += 1;
      continue;
    }

    if (key === '--admin-user' && value) {
      out.adminUser = value;
      i += 1;
      continue;
    }

    if (key === '--admin-password' && value) {
      out.adminPassword = value;
      i += 1;
      continue;
    }
  }

  return out;
}

function extractSetCookie(headers) {
  if (typeof headers.getSetCookie === 'function') {
    return headers.getSetCookie();
  }

  const single = headers.get('set-cookie');
  if (!single) return [];
  return [single];
}

function createCookieJar() {
  const map = new Map();

  return {
    addFromResponse(headers) {
      for (const raw of extractSetCookie(headers)) {
        const pair = String(raw).split(';', 1)[0] || '';
        const [name, ...rest] = pair.split('=');
        if (!name || rest.length === 0) continue;
        map.set(name.trim(), rest.join('=').trim());
      }
    },

    asHeader() {
      return [...map.entries()].map(([k, v]) => `${k}=${v}`).join('; ');
    },
  };
}

async function fetchWithCookies(url, options, jar) {
  const headers = {
    ...(options.headers || {}),
  };

  const cookie = jar.asHeader();
  if (cookie) {
    headers.Cookie = cookie;
  }

  const response = await fetch(url, {
    ...options,
    headers,
    redirect: 'manual',
  });

  jar.addFromResponse(response.headers);
  return response;
}

async function loginAndGetDashboard(base, adminUser, adminPassword) {
  const origin = base.replace(/\/$/, '');
  const loginUrl = `${origin}/wp-login.php`;
  const dashboardPath = '/wp-admin/admin.php?page=poradnik-pro-kpi';
  const dashboardUrl = `${origin}${dashboardPath}`;
  const jar = createCookieJar();

  // Prime test cookie.
  await fetchWithCookies(loginUrl, { method: 'GET' }, jar);

  const body = new URLSearchParams({
    log: adminUser,
    pwd: adminPassword,
    'wp-submit': 'Log In',
    redirect_to: dashboardUrl,
    testcookie: '1',
  });

  const loginResponse = await fetchWithCookies(
    loginUrl,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body,
    },
    jar
  );

  if (![302, 303].includes(loginResponse.status)) {
    throw new Error(`Login failed, expected redirect, got status ${loginResponse.status}`);
  }

  let next = loginResponse.headers.get('location') || dashboardPath;
  if (next.startsWith('/')) {
    next = `${origin}${next}`;
  }

  let response = await fetchWithCookies(next, { method: 'GET' }, jar);
  let redirects = 0;

  while ([301, 302, 303].includes(response.status) && redirects < 5) {
    let location = response.headers.get('location') || dashboardPath;
    if (location.startsWith('/')) {
      location = `${origin}${location}`;
    }
    response = await fetchWithCookies(location, { method: 'GET' }, jar);
    redirects += 1;
  }

  if (response.status !== 200) {
    throw new Error(`Dashboard request failed with status ${response.status}`);
  }

  const html = await response.text();

  if (html.includes('login_error') || html.includes('id="loginform"')) {
    throw new Error('Dashboard response indicates unauthenticated session');
  }

  return html;
}

function assertDashboardContract(html) {
  if (!html.includes('Poradnik KPI Dashboard')) {
    throw new Error('Missing KPI dashboard title marker');
  }

  if (!html.includes('Top sources (14 dni)')) {
    throw new Error('Missing top sources section marker');
  }

  const alpha = html.match(/<tr>\s*<td>\s*alpha\s*<\/td>\s*<td>\s*(\d+)\s*<\/td>\s*<\/tr>/i);
  const beta = html.match(/<tr>\s*<td>\s*beta\s*<\/td>\s*<td>\s*(\d+)\s*<\/td>\s*<\/tr>/i);

  if (!alpha || !beta) {
    throw new Error('Missing alpha/beta rows in top_sources table');
  }

  if (alpha[1] !== '2' || beta[1] !== '2') {
    throw new Error(`Unexpected alpha/beta tie counts: alpha=${alpha[1]}, beta=${beta[1]}`);
  }

  if (alpha.index > beta.index) {
    throw new Error('Tie-order violation: expected alpha row before beta row');
  }
}

async function main() {
  const { base, adminUser, adminPassword } = parseArgs(process.argv.slice(2));

  console.log('KPI dashboard E2E HTTP integration test');
  console.log(`base: ${base}`);
  console.log('');

  const html = await loginAndGetDashboard(base, adminUser, adminPassword);
  assertDashboardContract(html);

  console.log('✓ Authenticated dashboard access');
  console.log('✓ Top sources tie-order alpha -> beta (count 2/2)');
  console.log('');
  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
