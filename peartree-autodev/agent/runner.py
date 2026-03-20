from __future__ import annotations

import json
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

if __package__ in (None, ""):
    sys.path.append(str(Path(__file__).resolve().parent.parent))
    from agent import coder, git as git_ops, planner, reviewer
else:
    from . import coder, git as git_ops, planner, reviewer


def load_config(config_path: Path) -> dict[str, Any]:
    raw = config_path.read_text(encoding="utf-8").strip()
    try:
        return json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"agent.yaml must be JSON-compatible YAML. Parse error: {exc}") from exc


def load_context(context_path: Path) -> dict[str, Any]:
    if not context_path.exists():
        return {"cycles": 0, "last_commit": "", "last_task": None}
    try:
        return json.loads(context_path.read_text(encoding="utf-8"))
    except Exception:
        return {"cycles": 0, "last_commit": "", "last_task": None}


def save_context(context_path: Path, payload: dict[str, Any]) -> None:
    context_path.parent.mkdir(parents=True, exist_ok=True)
    context_path.write_text(json.dumps(payload, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")


def append_log(log_file: Path, payload: dict[str, Any]) -> None:
    log_file.parent.mkdir(parents=True, exist_ok=True)
    with log_file.open("a", encoding="utf-8") as fh:
        fh.write(json.dumps(payload, ensure_ascii=True) + "\n")


def main() -> int:
    base = Path(__file__).resolve().parent.parent
    config_path = base / "config" / "agent.yaml"
    memory_path = base / "memory" / "context.json"
    log_file = base / "logs" / "runner.log.jsonl"
    logs_path = base / "logs"

    config = load_config(config_path)
    repo_path = Path(os.environ.get("AUTODEV_REPO_PATH") or str(config.get("repo_path") or ".")).resolve()

    context = load_context(memory_path)
    loop_seconds = int(config.get("loop_seconds", 120))
    max_cycles = int(os.environ.get("AUTODEV_MAX_CYCLES") or config.get("max_cycles", 0))
    do_push = bool(config.get("push", False))
    git_remote = str(config.get("git_remote", "origin"))
    git_branch = str(config.get("git_branch", "main"))

    while True:
        cycle = int(context.get("cycles", 0)) + 1
        started = datetime.now(timezone.utc).isoformat()

        analysis = planner.analyze(repo_path)
        task = planner.decide(analysis, context)

        cycle_log: dict[str, Any] = {
            "cycle": cycle,
            "started_at": started,
            "task": task.as_dict() if task else None,
            "analysis_summary": {
                "todo_candidates": len(analysis.get("todo_candidates", [])),
                "dirty_files": len(analysis.get("dirty_files", [])),
            },
        }

        if task is None:
            cycle_log["status"] = "idle"
            append_log(log_file, cycle_log)
            context["cycles"] = cycle
            save_context(memory_path, context)
            if max_cycles > 0 and cycle >= max_cycles:
                break
            time.sleep(loop_seconds)
            continue

        impl = coder.implement(repo_path, task, config, logs_path)
        cycle_log["implement"] = {
            "ok": impl.ok,
            "notes": impl.notes,
            "changed_files": impl.changed_files,
        }

        validation = reviewer.validate(repo_path, impl.changed_files, config)
        cycle_log["validation"] = {
            "ok": validation.ok,
            "checks": validation.checks,
        }

        committed = False
        pushed = False
        commit_sha = ""

        if impl.ok and validation.ok and impl.changed_files:
            git_ops.stage_files(repo_path, impl.changed_files)
            ok, commit_out = git_ops.commit(repo_path, planner.make_commit_message(task))
            committed = ok
            commit_sha = commit_out if ok else ""
            cycle_log["commit"] = {"ok": ok, "output": commit_out}

            if ok and do_push:
                push_ok, push_out = git_ops.push(repo_path, git_remote, git_branch)
                pushed = push_ok
                cycle_log["push"] = {"ok": push_ok, "output": push_out}
        else:
            cycle_log["commit"] = {"ok": False, "output": "Skipped due to implementation/validation status or no changed files."}

        context["cycles"] = cycle
        context["last_task"] = task.as_dict()
        context["last_commit"] = commit_sha if committed else context.get("last_commit", "")
        context["last_cycle_status"] = {
            "implemented": impl.ok,
            "validated": validation.ok,
            "committed": committed,
            "pushed": pushed,
        }

        save_context(memory_path, context)
        append_log(log_file, cycle_log)

        if max_cycles > 0 and cycle >= max_cycles:
            break

        time.sleep(loop_seconds)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
