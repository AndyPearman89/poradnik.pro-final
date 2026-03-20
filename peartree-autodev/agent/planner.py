from __future__ import annotations

import json
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Any


@dataclass
class Task:
    kind: str
    title: str
    file: str | None = None
    line: int | None = None
    details: str | None = None

    def as_dict(self) -> dict[str, Any]:
        return {
            "kind": self.kind,
            "title": self.title,
            "file": self.file,
            "line": self.line,
            "details": self.details,
        }


def _run(command: list[str], cwd: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=str(cwd), text=True, capture_output=True, check=False)


def analyze(repo_path: Path) -> dict[str, Any]:
    todos: list[dict[str, Any]] = []
    grep_cmd = ["grep", "-RInE", "\\b(TODO|FIXME|XXX)\\b", ".", "--exclude-dir=.git"]
    proc = _run(grep_cmd, repo_path)
    if proc.returncode in (0, 1):
        for raw in proc.stdout.splitlines():
            # path:line:content
            m = re.match(r"^(.*?):(\d+):(.*)$", raw)
            if not m:
                continue
            content = m.group(3).strip()
            upper = content.upper()
            if upper.startswith("NO TODO") or upper.startswith("NO FIXME"):
                continue

            todos.append(
                {
                    "file": m.group(1).lstrip("./"),
                    "line": int(m.group(2)),
                    "content": content,
                }
            )

    status_proc = _run(["git", "status", "--porcelain"], repo_path)
    dirty = [line.strip() for line in status_proc.stdout.splitlines() if line.strip()]

    return {
        "todo_candidates": todos,
        "dirty_files": dirty,
        "repo_root": str(repo_path),
    }


def decide(analysis: dict[str, Any], context: dict[str, Any]) -> Task | None:
    todos = analysis.get("todo_candidates", [])
    for item in todos:
        file_path = str(item.get("file", ""))
        if file_path.startswith("peartree-autodev/"):
            continue
        return Task(
            kind="todo",
            title="Resolve TODO/FIXME candidate",
            file=file_path,
            line=int(item.get("line") or 0),
            details=str(item.get("content", "")),
        )

    return Task(
        kind="validation",
        title="Run repository validation cycle",
        details="No TODO/FIXME candidates found; executing validation-only cycle.",
    )


def make_commit_message(task: Task) -> str:
    if task.kind == "todo" and task.file:
        return f"autodev: address TODO in {task.file}:{task.line}"
    if task.kind == "validation":
        return "autodev: validation cycle"
    return "autodev: automated repository improvement"


def write_analysis_log(log_path: Path, payload: dict[str, Any]) -> None:
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(json.dumps(payload, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")
