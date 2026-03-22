#!/usr/bin/env node

/**
 * TASK-H03 – Production Metrics Checker
 *
 * Validates current production KPI metrics against defined targets:
 *   - CR   (Conversion Rate)       – lead form submissions / unique sessions
 *   - EPC  (Earnings Per Click)    – estimated revenue / affiliate clicks
 *   - RPM  (Revenue Per Mille)     – (revenue / pageviews) * 1000
 *   - CWV  (Core Web Vitals)       – LCP, CLS, TBT from Lighthouse history
 *
 * Usage:
 *   # Validate metrics from KPI store export + Lighthouse history:
 *   node scripts/check-production-metrics.mjs
 *
 *   # Override individual targets:
 *   node scripts/check-production-metrics.mjs --min-cr 0.02 --min-epc 0.05 --min-rpm 1.5
 *
 *   # Read metrics from a JSON file (for CI/automated runs):
 *   node scripts/check-production-metrics.mjs --metrics-file /path/to/metrics.json
 *
 *   # Emit JSON report:
 *   node scripts/check-production-metrics.mjs --json
 *
 * Exit codes:
 *   0 – all targets met
 *   1 – one or more targets missed
 *   2 – argument / input error
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { readdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const REPORTS_DIR = resolve(__dirname, '../docs/implementation/reports');
const LH_HISTORY_FILE = resolve(REPORTS_DIR, 'lighthouse-mobile-history.json');

// ── Production Targets (TASK-H03) ────────────────────────────────────────────
//
//  These are minimum acceptable values in production.
//  See docs/implementation/production-metrics-targets.md for rationale.
//
const TARGETS = {
  cr:  { min: 0.02,  label: 'Conversion Rate (CR)',      unit: '%',  scale: 100 },
  epc: { min: 0.05,  label: 'Earnings Per Click (EPC)',  unit: 'PLN', scale: 1 },
  rpm: { min: 2.00,  label: 'Revenue Per Mille (RPM)',   unit: 'PLN', scale: 1 },
  cwv: {
    maxLcp: 2500,   // ms – Good threshold per Google
    maxCls: 0.10,   // unitless
    maxTbt: 300,    // ms (proxy for FID/INP in lab)
  },
};

// ── CLI arg parsing ───────────────────────────────────────────────────────────

function parseArgs(argv) {
  const args = {
    minCr:       TARGETS.cr.min,
    minEpc:      TARGETS.epc.min,
    minRpm:      TARGETS.rpm.min,
    maxLcp:      TARGETS.cwv.maxLcp,
    maxCls:      TARGETS.cwv.maxCls,
    maxTbt:      TARGETS.cwv.maxTbt,
    metricsFile: null,
    jsonOutput:  false,
    reportOut:   null,
  };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--min-cr'       && val) { args.minCr      = parseFloat(val); i++; }
    else if (key === '--min-epc' && val) { args.minEpc     = parseFloat(val); i++; }
    else if (key === '--min-rpm' && val) { args.minRpm     = parseFloat(val); i++; }
    else if (key === '--max-lcp' && val) { args.maxLcp     = parseInt(val, 10); i++; }
    else if (key === '--max-cls' && val) { args.maxCls     = parseFloat(val); i++; }
    else if (key === '--max-tbt' && val) { args.maxTbt     = parseInt(val, 10); i++; }
    else if (key === '--metrics-file' && val) { args.metricsFile = resolve(val); i++; }
    else if (key === '--json')   { args.jsonOutput = true; }
    else if (key === '--report-out' && val) { args.reportOut = resolve(val); i++; }
  }

  return args;
}

// ── Data loaders ──────────────────────────────────────────────────────────────

function loadMetricsFile(path) {
  if (!existsSync(path)) {
    console.error(`ERROR: Metrics file not found: ${path}`);
    process.exit(2);
  }
  return JSON.parse(readFileSync(path, 'utf8'));
}

/** Load the latest Lighthouse history entry for CWV data */
function loadLatestCWV() {
  if (!existsSync(LH_HISTORY_FILE)) {
    return null;
  }
  try {
    const history = JSON.parse(readFileSync(LH_HISTORY_FILE, 'utf8'));
    if (!Array.isArray(history) || history.length === 0) return null;
    // Sort by date desc and return latest
    return [...history].sort((a, b) => {
      const da = a.date ?? a.timestamp ?? '';
      const db = b.date ?? b.timestamp ?? '';
      return db.localeCompare(da);
    })[0];
  } catch {
    return null;
  }
}

/** Find latest track-load report for RPM/EPC/CR data */
function loadLatestKpiReport() {
  if (!existsSync(REPORTS_DIR)) return null;
  const files = readdirSync(REPORTS_DIR)
    .filter(f => f.startsWith('track-load-report-') && f.endsWith('.md'))
    .sort()
    .reverse();
  if (files.length === 0) return null;
  return readFileSync(resolve(REPORTS_DIR, files[0]), 'utf8');
}

/** Attempt to parse CR/EPC/RPM from a KPI report markdown */
function parseKpiReport(md) {
  const metrics = {};
  // Patterns based on integration-test-kpi-dashboard output
  const crMatch   = md.match(/conversion[_\s-]*rate[^:]*:\s*([\d.]+)/i);
  const epcMatch  = md.match(/epc[^:]*:\s*([\d.]+)/i);
  const rpmMatch  = md.match(/rpm[^:]*:\s*([\d.]+)/i);

  if (crMatch)  metrics.cr  = parseFloat(crMatch[1]);
  if (epcMatch) metrics.epc = parseFloat(epcMatch[1]);
  if (rpmMatch) metrics.rpm = parseFloat(rpmMatch[1]);

  return metrics;
}

// ── Metric validation ─────────────────────────────────────────────────────────

function checkMetric(label, value, target, mode, unit = '', scale = 1) {
  const display = value !== null ? (value * scale).toFixed(3) : 'N/A';
  const targetDisplay = (target * scale).toFixed(3);
  let pass = false;

  if (value === null || value === undefined) {
    return { label, value, display, targetDisplay, unit, pass: null, message: 'NO DATA' };
  }

  pass = mode === 'min' ? value >= target : value <= target;
  const direction = mode === 'min' ? '≥' : '≤';
  return {
    label,
    value,
    display,
    targetDisplay,
    unit,
    pass,
    message: `${display}${unit} ${pass ? '✅' : '❌'} (target: ${direction} ${targetDisplay}${unit})`,
  };
}

// ── Reporting ─────────────────────────────────────────────────────────────────

function printReport(results, args) {
  console.log('');
  console.log('╔══════════════════════════════════════════════════╗');
  console.log('║        Production Metrics Check (TASK-H03)       ║');
  console.log('╚══════════════════════════════════════════════════╝');

  const groups = [
    { title: 'Business KPIs', keys: ['cr', 'epc', 'rpm'] },
    { title: 'Core Web Vitals', keys: ['lcp', 'cls', 'tbt'] },
  ];

  let allPass = true;
  let hasData = false;

  for (const group of groups) {
    console.log(`\n  ${group.title}:`);
    for (const key of group.keys) {
      const r = results[key];
      if (!r) continue;
      if (r.pass === null) {
        console.log(`    ⚠️   ${r.label}: ${r.message}`);
      } else {
        console.log(`    ${r.pass ? '✅' : '❌'}  ${r.label}: ${r.message}`);
        if (!r.pass) allPass = false;
        hasData = true;
      }
    }
  }

  console.log('');
  if (!hasData) {
    console.log('  ⚠️  WARNING: No metric data available – cannot validate targets.');
    console.log('      Run with --metrics-file to supply data or ensure reports exist.');
  } else if (allPass) {
    console.log('  ✅  ALL TARGETS MET');
  } else {
    console.log('  ❌  ONE OR MORE TARGETS MISSED');
  }
  console.log('');

  return { allPass, hasData };
}

function buildJsonReport(results, allPass, hasData, args) {
  return {
    timestamp: new Date().toISOString(),
    allPass,
    hasData,
    targets: {
      cr:  args.minCr,
      epc: args.minEpc,
      rpm: args.minRpm,
      lcp: args.maxLcp,
      cls: args.maxCls,
      tbt: args.maxTbt,
    },
    results: Object.fromEntries(
      Object.entries(results).map(([k, v]) => [k, {
        label:   v?.label,
        value:   v?.value,
        pass:    v?.pass,
        message: v?.message,
      }])
    ),
  };
}

// ── Main ──────────────────────────────────────────────────────────────────────

const args = parseArgs(process.argv.slice(2));

// Load metrics from file or auto-discover
let metrics = {};
if (args.metricsFile) {
  metrics = loadMetricsFile(args.metricsFile);
} else {
  // Auto: try latest KPI report for business metrics
  const kpiMd = loadLatestKpiReport();
  if (kpiMd) {
    const parsed = parseKpiReport(kpiMd);
    Object.assign(metrics, parsed);
  }
}

// Auto-load CWV from Lighthouse history
const latestCWV = loadLatestCWV();
if (latestCWV) {
  if (metrics.lcp === undefined && latestCWV.lcp_ms  !== undefined) metrics.lcp = latestCWV.lcp_ms;
  if (metrics.cls === undefined && latestCWV.cls      !== undefined) metrics.cls = latestCWV.cls;
  if (metrics.tbt === undefined && latestCWV.tbt_ms   !== undefined) metrics.tbt = latestCWV.tbt_ms;
}

// Build results
const results = {
  cr:  checkMetric('Conversion Rate (CR)',      metrics.cr  ?? null, args.minCr,  'min', '%',   100),
  epc: checkMetric('Earnings Per Click (EPC)',  metrics.epc ?? null, args.minEpc, 'min', ' PLN'),
  rpm: checkMetric('Revenue Per Mille (RPM)',   metrics.rpm ?? null, args.minRpm, 'min', ' PLN'),
  lcp: checkMetric('LCP',                       metrics.lcp ?? null, args.maxLcp, 'max', 'ms'),
  cls: checkMetric('CLS',                       metrics.cls ?? null, args.maxCls, 'max', ''),
  tbt: checkMetric('TBT',                       metrics.tbt ?? null, args.maxTbt, 'max', 'ms'),
};

const { allPass, hasData } = printReport(results, args);

if (args.jsonOutput) {
  const report = buildJsonReport(results, allPass, hasData, args);
  const json = JSON.stringify(report, null, 2);
  console.log(json);
  if (args.reportOut) {
    mkdirSync(dirname(args.reportOut), { recursive: true });
    writeFileSync(args.reportOut, json + '\n', 'utf8');
    console.log(`\nReport written to: ${args.reportOut}`);
  }
}

// Exit with failure if data was available and targets were missed
if (hasData && !allPass) {
  process.exit(1);
}
process.exit(0);
