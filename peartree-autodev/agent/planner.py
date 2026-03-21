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
    tasklist_candidates: list[dict[str, Any]] = []
    ignored_prefixes = (
        "peartree-autodev/",
        "memory/",
    )
    ignored_suffixes = (
        ".json",
        ".jsonl",
    )
    grep_cmd = ["grep", "-RInE", "\\b(TODO|FIXME|XXX)\\b", ".", "--exclude-dir=.git"]
    proc = _run(grep_cmd, repo_path)
    if proc.returncode in (0, 1):
        for raw in proc.stdout.splitlines():
            # path:line:content
            m = re.match(r"^(.*?):(\d+):(.*)$", raw)
            if not m:
                continue
            rel_file = m.group(1).lstrip("./")
            if rel_file.startswith(ignored_prefixes):
                continue
            if rel_file.endswith(ignored_suffixes):
                continue
            content = m.group(3).strip()
            upper = content.upper()
            if upper.startswith("NO TODO") or upper.startswith("NO FIXME"):
                continue

            todos.append(
                {
                    "file": rel_file,
                    "line": int(m.group(2)),
                    "content": content,
                }
            )

    status_proc = _run(["git", "status", "--porcelain"], repo_path)
    dirty = [line.strip() for line in status_proc.stdout.splitlines() if line.strip()]

    tasklist_path = repo_path / "docs" / "implementation" / "final-project-tasklist.md"
    if tasklist_path.exists():
        pattern = re.compile(r"^- \[(OPEN)\] (TASK-[A-Z]\d{2}) - (.+)$")
        for idx, raw in enumerate(tasklist_path.read_text(encoding="utf-8").splitlines(), start=1):
            match = pattern.match(raw.strip())
            if not match:
                continue
            tasklist_candidates.append(
                {
                    "file": "docs/implementation/final-project-tasklist.md",
                    "line": idx,
                    "status": match.group(1),
                    "task_id": match.group(2),
                    "content": match.group(3),
                }
            )

    return {
        "todo_candidates": todos,
        "tasklist_candidates": tasklist_candidates,
        "dirty_files": dirty,
        "repo_root": str(repo_path),
    }


def decide(analysis: dict[str, Any], context: dict[str, Any]) -> Task | None:
    tasklist_candidates = analysis.get("tasklist_candidates", [])
    for item in tasklist_candidates:
        return Task(
            kind="tasklist",
            title="Execute tasklist item",
            file=str(item.get("file") or ""),
            line=int(item.get("line") or 0),
            details=f"{item.get('task_id')}: {item.get('content')}",
        )

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
    if task.kind == "tasklist" and task.details:
        return f"autodev: execute {task.details.split(':', 1)[0]}"
    if task.kind == "todo" and task.file:
        return f"autodev: address TODO in {task.file}:{task.line}"
    if task.kind == "validation":
        return "autodev: validation cycle"
    return "autodev: automated repository improvement"


def write_analysis_log(log_path: Path, payload: dict[str, Any]) -> None:
    log_path.parent.mkdir(parents=True, exist_ok=True)
    log_path.write_text(json.dumps(payload, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")
