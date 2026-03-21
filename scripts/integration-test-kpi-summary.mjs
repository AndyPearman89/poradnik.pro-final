#!/usr/bin/env node

/**
 * Integration test: KPI summary contract marker.
 *
 * This script executes service unit tests and asserts the
 * multi-day tie-order integration contract is present.
 */

import { spawnSync } from 'node:child_process';

function fail(message) {
    console.error(`kpi-summary-test: ${message}`);
    process.exit(1);
}

const result = spawnSync('php', ['scripts/unit-test-services.php'], {
    encoding: 'utf8',
});

const output = `${result.stdout || ''}${result.stderr || ''}`;

if ((result.status ?? 1) !== 0) {
    process.stdout.write(output);
    fail('service unit suite failed');
}

if (!output.includes('AnalyticsService::ingestEvent + buildSummary multi-day tie-order integration')) {
    process.stdout.write(output);
    fail('missing multi-day tie-order integration marker');
}

if (!output.includes('Overall: PASS')) {
    process.stdout.write(output);
    fail('missing PASS marker');
}

console.log('kpi-summary-test: PASS');
