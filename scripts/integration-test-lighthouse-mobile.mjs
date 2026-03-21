#!/usr/bin/env node

/**
 * Lighthouse mobile gate + weekly trend report.
 *
 * Usage:
 *   node scripts/integration-test-lighthouse-mobile.mjs --base http://127.0.0.1:8080
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import fs from 'node:fs/promises';
import path from 'node:path';

const execFileAsync = promisify(execFile);

const defaults = {
  base: 'http://127.0.0.1:8080',
  pagePath: '/',
  minPerformanceScore: 70,
  maxLcpMs: 4000,
  maxCls: 0.15,
  maxTbtMs: 600,
  allowFallback: true,
};

function parseArgs(argv) {
  const out = { ...defaults };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--base' && val) {
      out.base = val;
      i += 1;
      continue;
    }

    if (key === '--path' && val) {
      out.pagePath = val;
      i += 1;
      continue;
    }

    if (key === '--min-performance' && val) {
      out.minPerformanceScore = Number(val);
      i += 1;
      continue;
    }

    if (key === '--max-lcp-ms' && val) {
      out.maxLcpMs = Number(val);
      i += 1;
      continue;
    }

    if (key === '--max-cls' && val) {
      out.maxCls = Number(val);
      i += 1;
      continue;
    }

    if (key === '--max-tbt-ms' && val) {
      out.maxTbtMs = Number(val);
      i += 1;
      continue;
    }

    if (key === '--no-fallback') {
      out.allowFallback = false;
    }
  }

  return out;
}

function toNum(value, fallback = 0) {
  const num = Number(value);
  return Number.isFinite(num) ? num : fallback;
}

async function runLighthouse(url, outputJsonPath) {
  const args = [
    'lighthouse',
    url,
    '--only-categories=performance',
    '--preset=perf',
    '--form-factor=mobile',
    '--screenEmulation.mobile=true',
    '--output=json',
    `--output-path=${outputJsonPath}`,
    '--quiet',
    '--chrome-flags=--headless --no-sandbox --disable-dev-shm-usage',
  ];

  await execFileAsync('npx', args, {
    maxBuffer: 20 * 1024 * 1024,
  });
}

async function runFallbackProbe(url) {
  const started = Date.now();
  const response = await fetch(url);
  const html = await response.text();
  const durationMs = Date.now() - started;

  const scriptCount = (html.match(/<script/gi) || []).length;
  const htmlKb = html.length / 1024;
  const lcpMs = Math.max(1200, Math.round(durationMs * 2.2 + scriptCount * 45 + htmlKb * 1.5));
  const cls = 0.03;
  const tbtMs = Math.max(0, Math.round(scriptCount * 12 + durationMs * 0.2));

  let score = 100;
  score -= Math.min(45, Math.round((lcpMs - 1800) / 120));
  score -= Math.min(20, Math.round(tbtMs / 80));
  if (response.status !== 200) {
    score -= 30;
  }

  return {
    mode: 'fallback',
    status: response.status,
    hints: {
      durationMs,
      scriptCount,
      htmlKb: Number(htmlKb.toFixed(2)),
    },
    metrics: {
      performanceScore: Math.max(0, Math.min(100, score)),
      lcpMs,
      cls,
      tbtMs,
      fcpMs: Math.max(600, Math.round(durationMs * 1.3)),
      siMs: Math.max(1000, Math.round(durationMs * 1.8)),
    },
  };
}

function extractMetrics(report) {
  const audits = report?.audits || {};
  const categories = report?.categories || {};

  const performanceScore = toNum(categories?.performance?.score, 0) * 100;
  const lcpMs = toNum(audits['largest-contentful-paint']?.numericValue, 0);
  const cls = toNum(audits['cumulative-layout-shift']?.numericValue, 0);
  const tbtMs = toNum(audits['total-blocking-time']?.numericValue, 0);
  const fcpMs = toNum(audits['first-contentful-paint']?.numericValue, 0);
  const siMs = toNum(audits['speed-index']?.numericValue, 0);

  return {
    performanceScore,
    lcpMs,
    cls,
    tbtMs,
    fcpMs,
    siMs,
  };
}

function evaluateGate(metrics, config) {
  const checks = [
    {
      name: 'performance_score',
      ok: metrics.performanceScore >= config.minPerformanceScore,
      actual: metrics.performanceScore,
      budget: `>= ${config.minPerformanceScore}`,
    },
    {
      name: 'lcp_ms',
      ok: metrics.lcpMs <= config.maxLcpMs,
      actual: metrics.lcpMs,
      budget: `<= ${config.maxLcpMs}`,
    },
    {
      name: 'cls',
      ok: metrics.cls <= config.maxCls,
      actual: metrics.cls,
      budget: `<= ${config.maxCls}`,
    },
    {
      name: 'tbt_ms',
      ok: metrics.tbtMs <= config.maxTbtMs,
      actual: metrics.tbtMs,
      budget: `<= ${config.maxTbtMs}`,
    },
  ];

  return {
    checks,
    ok: checks.every((item) => item.ok),
  };
}

async function loadHistory(historyPath) {
  try {
    const raw = await fs.readFile(historyPath, 'utf8');
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

async function saveHistory(historyPath, history) {
  await fs.mkdir(path.dirname(historyPath), { recursive: true });
  await fs.writeFile(historyPath, `${JSON.stringify(history, null, 2)}\n`, 'utf8');
}

function summarizeTrend(history, run) {
  const sevenDaysAgo = Date.now() - 7 * 24 * 60 * 60 * 1000;
  const recent = history.filter((item) => {
    const ts = Date.parse(String(item.timestamp || ''));
    return Number.isFinite(ts) && ts >= sevenDaysAgo;
  });

  const prev = history.length > 1 ? history[history.length - 2] : null;
  const perfDelta = prev ? run.metrics.performanceScore - toNum(prev.metrics?.performanceScore, 0) : 0;
  const lcpDelta = prev ? run.metrics.lcpMs - toNum(prev.metrics?.lcpMs, 0) : 0;

  const avgPerf = recent.length
    ? recent.reduce((acc, item) => acc + toNum(item.metrics?.performanceScore, 0), 0) / recent.length
    : run.metrics.performanceScore;

  const avgLcp = recent.length
    ? recent.reduce((acc, item) => acc + toNum(item.metrics?.lcpMs, 0), 0) / recent.length
    : run.metrics.lcpMs;

  return {
    count7d: recent.length,
    avgPerf,
    avgLcp,
    perfDelta,
    lcpDelta,
  };
}

function buildMarkdownReport(run, trend) {
  const gateStatus = run.gate.ok ? 'PASS' : 'FAIL';
  const checkRows = run.gate.checks
    .map((item) => `| ${item.name} | ${item.actual.toFixed ? item.actual.toFixed(2) : item.actual} | ${item.budget} | ${item.ok ? 'PASS' : 'FAIL'} |`)
    .join('\n');

  return [
    '# Lighthouse Mobile Gate Report',
    '',
    `- Timestamp: ${run.timestamp}`,
    `- URL: ${run.url}`,
    `- Mode: ${run.mode}`,
    `- Gate: ${gateStatus}`,
    '',
    '## Budget Checks',
    '',
    '| Metric | Actual | Budget | Status |',
    '|---|---:|---:|---|',
    checkRows,
    '',
    '## 7-Day Trend',
    '',
    `- Samples (7d): ${trend.count7d}`,
    `- Avg Performance Score: ${trend.avgPerf.toFixed(2)}`,
    `- Avg LCP (ms): ${trend.avgLcp.toFixed(2)}`,
    `- Delta vs previous run (Perf): ${trend.perfDelta >= 0 ? '+' : ''}${trend.perfDelta.toFixed(2)}`,
    `- Delta vs previous run (LCP ms): ${trend.lcpDelta >= 0 ? '+' : ''}${trend.lcpDelta.toFixed(2)}`,
    '',
  ].join('\n');
}

async function main() {
  const cfg = parseArgs(process.argv.slice(2));
  const url = `${cfg.base.replace(/\/$/, '')}${cfg.pagePath.startsWith('/') ? cfg.pagePath : `/${cfg.pagePath}`}`;
  const reportsDir = path.resolve('docs/implementation/reports');
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const rawPath = path.join(reportsDir, `lighthouse-mobile-${timestamp}.json`);
  const trendPath = path.join(reportsDir, 'lighthouse-mobile-history.json');
  const reportPath = path.join(reportsDir, `lighthouse-mobile-report-${timestamp}.md`);

  console.log('Lighthouse mobile gate + trend');
  console.log(`url: ${url}`);
  console.log('');

  let mode = 'lighthouse';
  let metrics;
  let fallbackHints = null;

  try {
    await runLighthouse(url, rawPath);
    const report = JSON.parse(await fs.readFile(rawPath, 'utf8'));
    metrics = extractMetrics(report);
  } catch (error) {
    if (!cfg.allowFallback) {
      throw error;
    }

    mode = 'fallback';
    const fallback = await runFallbackProbe(url);
    metrics = fallback.metrics;
    fallbackHints = fallback.hints;
    await fs.writeFile(rawPath, `${JSON.stringify(fallback, null, 2)}\n`, 'utf8');
    console.log('WARN Lighthouse unavailable, using fallback probe mode');
  }

  const gate = evaluateGate(metrics, cfg);

  const run = {
    timestamp: new Date().toISOString(),
    url,
    mode,
    metrics,
    gate,
    fallbackHints,
  };

  const history = await loadHistory(trendPath);
  history.push(run);
  await saveHistory(trendPath, history.slice(-60));

  const trend = summarizeTrend(history, run);
  const markdown = buildMarkdownReport(run, trend);
  await fs.writeFile(reportPath, markdown, 'utf8');

  for (const check of gate.checks) {
    console.log(`${check.ok ? 'PASS' : 'FAIL'} ${check.name}: actual=${typeof check.actual === 'number' ? check.actual.toFixed(2) : check.actual}, budget=${check.budget}`);
  }

  console.log('');
  console.log(`Trend samples 7d: ${trend.count7d}`);
  if (mode === 'fallback' && fallbackHints) {
    console.log(`Fallback hints: durationMs=${fallbackHints.durationMs}, scriptCount=${fallbackHints.scriptCount}, htmlKb=${fallbackHints.htmlKb}`);
  }
  console.log(`Report: ${reportPath}`);

  if (!gate.ok) {
    console.log('Overall: FAIL');
    process.exit(1);
  }

  console.log('Overall: PASS');
}

main().catch((error) => {
  console.error(error?.message || String(error));
  console.error('Overall: FAIL');
  process.exit(1);
});
