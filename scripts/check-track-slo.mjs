#!/usr/bin/env node

/**
 * Validates Baseline p95/p99 from the latest /track load-test report.
 *
 * Usage:
 *   node scripts/check-track-slo.mjs
 *   node scripts/check-track-slo.mjs --report docs/implementation/reports/track-load-report-YYYYMMDD-HHMMSS.md
 *   node scripts/check-track-slo.mjs --max-p95 2000 --max-p99 5000
 */

import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const defaults = {
  report: '',
  maxP95: Number.parseInt(process.env.TRACK_BASELINE_MAX_P95_MS || '2000', 10),
  maxP99: Number.parseInt(process.env.TRACK_BASELINE_MAX_P99_MS || '5000', 10),
};

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--report' && val) {
      out.report = val;
      i++;
      continue;
    }

    if (key === '--max-p95' && val) {
      const n = Number.parseInt(val, 10);
      if (Number.isFinite(n) && n > 0) out.maxP95 = n;
      i++;
      continue;
    }

    if (key === '--max-p99' && val) {
      const n = Number.parseInt(val, 10);
      if (Number.isFinite(n) && n > 0) out.maxP99 = n;
      i++;
    }
  }

  return out;
}

function latestReportPath() {
  const reportsDir = resolve(process.cwd(), 'docs/implementation/reports');
  const names = readdirSync(reportsDir)
    .filter((name) => /^track-load-report-\d{8}-\d{6}\.md$/.test(name))
    .sort();

  if (names.length === 0) {
    throw new Error('No track load reports found in docs/implementation/reports.');
  }

  return resolve(reportsDir, names[names.length - 1]);
}

function parseBaselineMetrics(markdown) {
  const lines = markdown.split(/\r?\n/);
  const row = lines.find((line) => line.startsWith('| Baseline |'));

  if (!row) {
    throw new Error('Baseline row not found in report table.');
  }

  const cols = row.split('|').map((c) => c.trim()).filter(Boolean);
  if (cols.length < 7) {
    throw new Error('Baseline row has unexpected format.');
  }

  const status = cols[1];
  const p95 = Number.parseFloat(cols[5]);
  const p99 = Number.parseFloat(cols[6]);

  if (!Number.isFinite(p95) || !Number.isFinite(p99)) {
    throw new Error('Could not parse Baseline p95/p99 values from report.');
  }

  return { status, p95, p99 };
}

function main() {
  const args = parseArgs(process.argv.slice(2));
  const reportPath = args.report ? resolve(process.cwd(), args.report) : latestReportPath();
  const content = readFileSync(reportPath, 'utf8');
  const baseline = parseBaselineMetrics(content);

  const passStatus = baseline.status === 'PASS';
  const passP95 = baseline.p95 <= args.maxP95;
  const passP99 = baseline.p99 <= args.maxP99;

  console.log('Track SLO check');
  console.log(`report: ${reportPath}`);
  console.log(`baseline_status: ${baseline.status}`);
  console.log(`baseline_p95_ms: ${baseline.p95}`);
  console.log(`baseline_p99_ms: ${baseline.p99}`);
  console.log(`max_p95_ms: ${args.maxP95}`);
  console.log(`max_p99_ms: ${args.maxP99}`);

  if (passStatus && passP95 && passP99) {
    console.log('result: PASS');
    return;
  }

  console.error('result: FAIL');
  if (!passStatus) console.error('reason: baseline scenario status is not PASS');
  if (!passP95) console.error('reason: baseline p95 is above threshold');
  if (!passP99) console.error('reason: baseline p99 is above threshold');
  process.exit(1);
}

main();
