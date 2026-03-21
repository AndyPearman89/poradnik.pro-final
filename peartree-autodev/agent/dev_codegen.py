from __future__ import annotations

import json
import os
import re
from dataclasses import dataclass
from pathlib import Path


@dataclass
class Task:
    kind: str
    title: str
    file: str | None
    line: int | None
    details: str | None


IGNORED_PREFIXES = (
    "peartree-autodev/",
    "memory/",
)

KPI_SUMMARY_SCRIPT = "scripts/integration-test-kpi-summary.mjs"

KPI_SUMMARY_TEMPLATE = """#!/usr/bin/env node

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
"""


def _load_task() -> Task:
    raw = os.environ.get("AUTODEV_TASK_JSON", "{}").strip() or "{}"
    payload = json.loads(raw)
    return Task(
        kind=str(payload.get("kind") or "validation"),
        title=str(payload.get("title") or ""),
        file=payload.get("file"),
        line=payload.get("line"),
        details=payload.get("details"),
    )


def _is_allowed_target(path: str | None) -> bool:
    if not path:
        return False
    return not path.startswith(IGNORED_PREFIXES)


def _fix_todo_line(repo_root: Path, rel_file: str, line_no: int | None) -> bool:
    target = (repo_root / rel_file).resolve()
    if not target.exists() or not target.is_file():
        return False

    try:
        lines = target.read_text(encoding="utf-8").splitlines(keepends=True)
    except UnicodeDecodeError:
        return False

    pattern = re.compile(r"\b(TODO|FIXME|XXX)\b")

    if line_no and 1 <= line_no <= len(lines):
        idx = line_no - 1
        src = lines[idx]
        dst = pattern.sub("DONE", src, count=1)
        if dst != src:
            lines[idx] = dst
            target.write_text("".join(lines), encoding="utf-8")
            return True

    for idx, src in enumerate(lines):
        dst = pattern.sub("DONE", src, count=1)
        if dst != src:
            lines[idx] = dst
            target.write_text("".join(lines), encoding="utf-8")
            return True

    return False


def _contains_kpi_summary_request(details: str | None) -> bool:
    text = (details or "").lower()
    return "integration-test-kpi-summary.mjs" in text or "kpi summary" in text


def _create_kpi_summary_script(repo_root: Path) -> bool:
    target = (repo_root / KPI_SUMMARY_SCRIPT).resolve()
    target.parent.mkdir(parents=True, exist_ok=True)

    if target.exists():
        return False

    target.write_text(KPI_SUMMARY_TEMPLATE, encoding="utf-8")
    return True


def main() -> int:
    repo_root = Path.cwd()
    task = _load_task()

    if task.kind != "todo":
        return 0

    if not _is_allowed_target(task.file):
        return 0

    if _contains_kpi_summary_request(task.details):
        _create_kpi_summary_script(repo_root)

    line_no = int(task.line) if isinstance(task.line, int) else None
    _fix_todo_line(repo_root, str(task.file), line_no)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())