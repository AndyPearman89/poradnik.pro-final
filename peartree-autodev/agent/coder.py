from __future__ import annotations

import json
import os
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .planner import Task


@dataclass
class ImplementationResult:
    ok: bool
    changed_files: list[str]
    notes: str


def _run(command: list[str], cwd: Path, env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=str(cwd), text=True, capture_output=True, check=False, env=env)


def _changed_files(repo_path: Path) -> list[str]:
    proc = _run(["git", "status", "--porcelain"], repo_path)
    files: list[str] = []
    for row in proc.stdout.splitlines():
        row = row.strip()
        if not row:
            continue
        files.append(row[3:].strip())
    return files


def implement(repo_path: Path, task: Task, config: dict[str, Any], logs_path: Path) -> ImplementationResult:
    codegen_cfg = (config.get("codegen") or {})
    command = str(codegen_cfg.get("command") or "").strip()

    if command:
        env = os.environ.copy()
        env["AUTODEV_TASK_JSON"] = json.dumps(task.as_dict(), ensure_ascii=True)
        proc = _run(["bash", "-lc", command], repo_path, env=env)
        if proc.returncode != 0:
            return ImplementationResult(
                ok=False,
                changed_files=[],
                notes=f"Codegen command failed: {proc.stderr.strip() or proc.stdout.strip()}",
            )
        return ImplementationResult(ok=True, changed_files=_changed_files(repo_path), notes="External codegen command completed.")

    # Safe deterministic fallback: no repository writes without explicit codegen command.
    logs_path.mkdir(parents=True, exist_ok=True)
    marker = logs_path / "last-task.json"
    marker.write_text(json.dumps(task.as_dict(), indent=2, ensure_ascii=True) + "\n", encoding="utf-8")

    return ImplementationResult(
        ok=True,
        changed_files=[],
        notes="No external codegen configured; repository changes skipped.",
    )
