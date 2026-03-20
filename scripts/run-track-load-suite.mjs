#!/usr/bin/env node

/**
 * Runs baseline + peak load tests for /track and writes a markdown report.
 *
 * Usage:
 *   node scripts/run-track-load-suite.mjs --base http://localhost:8080
 */

import { spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const defaults = {
  base: 'http://localhost:8080',
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
    }
  }

  return args;
}

function nowStamp() {
  const d = new Date();
  const y = String(d.getUTCFullYear());
  const m = String(d.getUTCMonth() + 1).padStart(2, '0');
  const day = String(d.getUTCDate()).padStart(2, '0');
  const hh = String(d.getUTCHours()).padStart(2, '0');
  const mm = String(d.getUTCMinutes()).padStart(2, '0');
  const ss = String(d.getUTCSeconds()).padStart(2, '0');
  return `${y}${m}${day}-${hh}${mm}${ss}`;
}

function parseOutput(output) {
  const lines = output.split(/\r?\n/);
  const map = {};

  for (const line of lines) {
    const idx = line.indexOf(':');
    if (idx === -1) {
      continue;
    }
    const key = line.slice(0, idx).trim();
    const val = line.slice(idx + 1).trim();
    map[key] = val;
  }

  return map;
}

function runScenario({ name, requests, concurrency, args }) {
  const cmdArgs = [
    'scripts/load-test-track.mjs',
    '--base', args.base,
    '--requests', String(requests),
    '--concurrency', String(concurrency),
    '--event', args.event,
    '--source', args.source,
  ];

  const result = spawnSync('node', cmdArgs, {
    cwd: process.cwd(),
    encoding: 'utf8',
  });

  const stdout = result.stdout || '';
  const stderr = result.stderr || '';
  const parsed = parseOutput(stdout);

  return {
    name,
    requests,
    concurrency,
    exitCode: result.status ?? 1,
    stdout,
    stderr,
    parsed,
  };
}

function scenarioRow(s) {
  const status = s.exitCode === 0 ? 'PASS' : 'FAIL';
  const rps = s.parsed.rps || '-';
  const p95 = s.parsed.latency_p95_ms || '-';
  const p99 = s.parsed.latency_p99_ms || '-';
  const ok = s.parsed.ok || '-';
  const failed = s.parsed.failed || '-';

  return `| ${s.name} | ${status} | ${ok} | ${failed} | ${rps} | ${p95} | ${p99} |`;
}

function formatScenarioDetails(s) {
  return [
    `### ${s.name}`,
    '',
    `- exit_code: ${s.exitCode}`,
    `- requests: ${s.requests}`,
    `- concurrency: ${s.concurrency}`,
    '',
    '```text',
    (s.stdout || '').trim() || '(no stdout)',
    '```',
    '',
    '```text',
    (s.stderr || '').trim() || '(no stderr)',
    '```',
    '',
  ].join('\n');
}

function main() {
  const args = parseArgs(process.argv.slice(2));

  const scenarios = [
    runScenario({ name: 'Baseline', requests: 500, concurrency: 15, args }),
    runScenario({ name: 'Peak', requests: 2000, concurrency: 50, args }),
  ];

  const reportDir = resolve(process.cwd(), 'docs/implementation/reports');
  mkdirSync(reportDir, { recursive: true });

  const stamp = nowStamp();
  const reportPath = resolve(reportDir, `track-load-report-${stamp}.md`);

  const allPassed = scenarios.every((s) => s.exitCode === 0);
  const overall = allPassed ? 'PASS' : 'FAIL';

  const lines = [
    '# Raport testow obciazeniowych /track',
    '',
    `- data_utc: ${new Date().toISOString()}`,
    `- base_url: ${args.base}`,
    `- event: ${args.event}`,
    `- source: ${args.source}`,
    `- overall: ${overall}`,
    '',
    '| Scenariusz | Status | OK | Failed | RPS | p95 (ms) | p99 (ms) |',
    '| --- | --- | ---: | ---: | ---: | ---: | ---: |',
    ...scenarios.map(scenarioRow),
    '',
    ...scenarios.map(formatScenarioDetails),
  ];

  writeFileSync(reportPath, `${lines.join('\n')}\n`, 'utf8');

  console.log(`Report written: ${reportPath}`);

  if (!allPassed) {
    process.exit(1);
  }
}

main();
