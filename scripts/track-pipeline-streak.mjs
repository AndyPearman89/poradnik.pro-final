#!/usr/bin/env node

/**
 * TASK-H02 – Pipeline Green Streak Tracker
 *
 * Tracks consecutive days of passing CI pipeline runs.
 * Reads streak state from a JSON file and updates it based on latest run result.
 *
 * Usage:
 *   # Record today's result (pass/fail):
 *   node scripts/track-pipeline-streak.mjs --result pass
 *   node scripts/track-pipeline-streak.mjs --result fail
 *
 *   # Query current streak (exit 0 if >= threshold, exit 1 otherwise):
 *   node scripts/track-pipeline-streak.mjs --check --threshold 7
 *
 *   # Show full history:
 *   node scripts/track-pipeline-streak.mjs --history
 *
 *   # Use custom state file:
 *   node scripts/track-pipeline-streak.mjs --state /path/to/streak.json --result pass
 *
 * Exit codes:
 *   0 – OK / streak meets threshold
 *   1 – streak below threshold (--check mode)
 *   2 – usage / argument error
 */

import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));

const DEFAULT_STATE_FILE = resolve(
  __dirname,
  '../docs/implementation/reports/pipeline-streak.json'
);
const TARGET_STREAK = 7;

// ── CLI arg parsing ──────────────────────────────────────────────────────────

function parseArgs(argv) {
  const args = {
    result: null,   // 'pass' | 'fail'
    check: false,
    history: false,
    threshold: TARGET_STREAK,
    stateFile: DEFAULT_STATE_FILE,
    date: null,     // override date (YYYY-MM-DD), useful for backfill
  };

  for (let i = 0; i < argv.length; i++) {
    const key = argv[i];
    const val = argv[i + 1];

    if (key === '--result' && val) {
      if (!['pass', 'fail'].includes(val)) {
        console.error('ERROR: --result must be "pass" or "fail"');
        process.exit(2);
      }
      args.result = val;
      i++;
    } else if (key === '--check') {
      args.check = true;
    } else if (key === '--history') {
      args.history = true;
    } else if (key === '--threshold' && val) {
      args.threshold = parseInt(val, 10);
      i++;
    } else if (key === '--state' && val) {
      args.stateFile = resolve(val);
      i++;
    } else if (key === '--date' && val) {
      args.date = val;
      i++;
    }
  }

  return args;
}

// ── State I/O ────────────────────────────────────────────────────────────────

/** @returns {{ streak: number, longestStreak: number, lastDate: string|null, history: Array<{date:string,result:string}> }} */
function loadState(stateFile) {
  if (!existsSync(stateFile)) {
    return { streak: 0, longestStreak: 0, lastDate: null, history: [] };
  }
  try {
    return JSON.parse(readFileSync(stateFile, 'utf8'));
  } catch {
    console.warn(`WARN: Could not parse state file ${stateFile}, starting fresh`);
    return { streak: 0, longestStreak: 0, lastDate: null, history: [] };
  }
}

function saveState(stateFile, state) {
  const dir = dirname(stateFile);
  if (!existsSync(dir)) {
    mkdirSync(dir, { recursive: true });
  }
  writeFileSync(stateFile, JSON.stringify(state, null, 2) + '\n', 'utf8');
}

// ── Date helpers ─────────────────────────────────────────────────────────────

function todayISO() {
  return new Date().toISOString().slice(0, 10);
}

function daysBetween(dateA, dateB) {
  const msPerDay = 86_400_000;
  return Math.round((new Date(dateB) - new Date(dateA)) / msPerDay);
}

// ── Core logic ───────────────────────────────────────────────────────────────

function recordResult(state, date, result) {
  const isPass = result === 'pass';

  // Check for duplicate entry on the same date
  const existing = state.history.findIndex(e => e.date === date);
  if (existing !== -1) {
    const prev = state.history[existing];
    if (prev.result === result) {
      console.log(`INFO: Entry for ${date} already recorded as "${result}", skipping duplicate.`);
      return state;
    }
    // Update existing entry
    console.log(`INFO: Updating existing entry for ${date}: ${prev.result} → ${result}`);
    state.history[existing] = { date, result };
  } else {
    state.history.push({ date, result });
  }

  // Sort history by date ascending
  state.history.sort((a, b) => a.date.localeCompare(b.date));

  // Recalculate streak from scratch (tail of consecutive passing days)
  let streak = 0;
  for (let i = state.history.length - 1; i >= 0; i--) {
    const entry = state.history[i];
    if (entry.result !== 'pass') break;

    if (i < state.history.length - 1) {
      const nextDate = state.history[i + 1].date;
      const gap = daysBetween(entry.date, nextDate);
      if (gap !== 1) break; // non-consecutive days break the streak
    }

    streak++;
  }

  state.streak = isPass ? streak : 0;
  state.lastDate = date;
  state.longestStreak = Math.max(state.longestStreak || 0, state.streak);

  return state;
}

function printStreakStatus(state, threshold) {
  const { streak, longestStreak, lastDate, history } = state;
  const remaining = Math.max(0, threshold - streak);
  const pct = Math.min(100, Math.round((streak / threshold) * 100));

  console.log('');
  console.log('╔══════════════════════════════════════════╗');
  console.log('║     Pipeline Green Streak Tracker        ║');
  console.log('╚══════════════════════════════════════════╝');
  console.log(`  Current streak : ${streak} / ${threshold} days ${streak >= threshold ? '✅ TARGET MET' : '🔄 in progress'}`);
  console.log(`  Longest streak : ${longestStreak} days`);
  console.log(`  Last recorded  : ${lastDate ?? 'none'}`);
  console.log(`  Progress       : ${'█'.repeat(Math.round(pct / 10))}${'░'.repeat(10 - Math.round(pct / 10))} ${pct}%`);
  if (remaining > 0) {
    console.log(`  Remaining      : ${remaining} more consecutive green day(s) needed`);
  }
  console.log(`  Total entries  : ${history.length}`);
  console.log('');
}

function printHistory(state) {
  console.log('\nPipeline Run History:');
  console.log('─────────────────────────');
  if (state.history.length === 0) {
    console.log('  (no entries yet)');
  } else {
    for (const entry of [...state.history].reverse().slice(0, 30)) {
      const icon = entry.result === 'pass' ? '✅' : '❌';
      console.log(`  ${icon}  ${entry.date}  ${entry.result.toUpperCase()}`);
    }
    if (state.history.length > 30) {
      console.log(`  ... and ${state.history.length - 30} older entries`);
    }
  }
  console.log('');
}

// ── Main ─────────────────────────────────────────────────────────────────────

const args = parseArgs(process.argv.slice(2));
let state = loadState(args.stateFile);

if (args.history) {
  printHistory(state);
  printStreakStatus(state, args.threshold);
  process.exit(0);
}

if (args.result) {
  const date = args.date ?? todayISO();
  state = recordResult(state, date, args.result);
  saveState(args.stateFile, state);
  console.log(`Recorded: ${date} → ${args.result.toUpperCase()}`);
  printStreakStatus(state, args.threshold);
}

if (args.check) {
  printStreakStatus(state, args.threshold);
  if (state.streak >= args.threshold) {
    console.log(`✅  PASS: Streak of ${state.streak} meets the ${args.threshold}-day target.`);
    process.exit(0);
  } else {
    console.log(`❌  FAIL: Streak of ${state.streak} does NOT meet the ${args.threshold}-day target.`);
    process.exit(1);
  }
}

if (!args.result && !args.check && !args.history) {
  // Default: print current status
  printStreakStatus(state, args.threshold);
  printHistory(state);
}
