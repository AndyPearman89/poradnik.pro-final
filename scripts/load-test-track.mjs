#!/usr/bin/env node

/**
 * Lightweight load test for /peartree/v1/track using native fetch (Node 18+).
 *
 * Usage:
 *   node scripts/load-test-track.mjs --base http://localhost:8080 --requests 500 --concurrency 20 --event cta_click --source affiliate
 */

const defaults = {
  base: 'http://localhost:8080',
  requests: 300,
  concurrency: 10,
  event: 'cta_click',
  source: 'affiliate',
};

function parseArgs(argv) {
  const args = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const value = argv[i + 1];

    if (!key.startsWith('--')) {
      continue;
    }

    const name = key.slice(2);
    if (['base', 'event', 'source'].includes(name) && value) {
      args[name] = value;
      i++;
      continue;
    }

    if (['requests', 'concurrency'].includes(name) && value) {
      const num = Number.parseInt(value, 10);
      if (Number.isFinite(num) && num > 0) {
        args[name] = num;
      }
      i++;
    }
  }

  return args;
}

function percentile(sorted, p) {
  if (sorted.length === 0) {
    return 0;
  }
  const idx = Math.min(sorted.length - 1, Math.max(0, Math.ceil((p / 100) * sorted.length) - 1));
  return sorted[idx];
}

async function probe(url, maxRetries = 3, delayMs = 300) {
  for (let attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      const res = await fetch(url, { method: 'OPTIONS' });
      if (res.ok || res.status === 401 || res.status === 403 || res.status === 404) {
        return true;
      }
    } catch {
      // network error — will retry
    }

    if (attempt < maxRetries) {
      await new Promise((r) => setTimeout(r, delayMs * attempt));
    }
  }

  return false;
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  const endpoint = `${args.base.replace(/\/$/, '')}/wp-json/peartree/v1/track`;

  const isReachable = await probe(endpoint);
  if (!isReachable) {
    console.error(`Endpoint unreachable: ${endpoint}`);
    console.error('Start WordPress locally and rerun this command with --base.');
    process.exit(2);
  }

  const durations = [];
  const statuses = new Map();
  let ok = 0;
  let failed = 0;

  let sent = 0;
  const startedAt = Date.now();

  async function worker() {
    while (sent < args.requests) {
      const index = sent;
      sent += 1;

      const body = {
        eventName: args.event,
        payload: {
          source: args.source,
          ts: Date.now(),
          idx: index,
        },
      };

      const reqStart = Date.now();
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(body),
        });

        const took = Date.now() - reqStart;
        durations.push(took);
        statuses.set(res.status, (statuses.get(res.status) || 0) + 1);

        if (res.status >= 200 && res.status < 300) {
          ok += 1;
        } else {
          failed += 1;
        }
      } catch {
        failed += 1;
      }
    }
  }

  const workers = [];
  for (let i = 0; i < args.concurrency; i++) {
    workers.push(worker());
  }
  await Promise.all(workers);

  const totalMs = Date.now() - startedAt;
  const sorted = [...durations].sort((a, b) => a - b);
  const avg = durations.length > 0 ? durations.reduce((a, b) => a + b, 0) / durations.length : 0;
  const rps = totalMs > 0 ? (args.requests / totalMs) * 1000 : 0;

  console.log('Load test result');
  console.log(`endpoint: ${endpoint}`);
  console.log(`requests: ${args.requests}`);
  console.log(`concurrency: ${args.concurrency}`);
  console.log(`ok: ${ok}`);
  console.log(`failed: ${failed}`);
  console.log(`duration_ms: ${totalMs}`);
  console.log(`rps: ${rps.toFixed(2)}`);
  console.log(`latency_avg_ms: ${avg.toFixed(2)}`);
  console.log(`latency_p50_ms: ${percentile(sorted, 50)}`);
  console.log(`latency_p95_ms: ${percentile(sorted, 95)}`);
  console.log(`latency_p99_ms: ${percentile(sorted, 99)}`);

  const statusSummary = [...statuses.entries()]
    .sort((a, b) => a[0] - b[0])
    .map(([code, count]) => `${code}:${count}`)
    .join(', ');
  console.log(`statuses: ${statusSummary || 'none'}`);

  if (failed > 0) {
    process.exit(1);
  }
}

main().catch((error) => {
  console.error(error?.message || String(error));
  process.exit(1);
});
