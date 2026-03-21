from __future__ import annotations

import argparse
import json
import os
import sys
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

if __package__ in (None, ""):
    sys.path.append(str(Path(__file__).resolve().parent.parent))
    from agent import planner, reviewer
    from agent.executor import execute_task
    from agent.logger import log
    from agent.memory import load_memory, save_memory
else:
    from . import planner, reviewer
    from .executor import execute_task
    from .logger import log
    from .memory import load_memory, save_memory


def load_config(config_path: Path) -> dict[str, Any]:
    raw = config_path.read_text(encoding="utf-8").strip()
    try:
        return json.loads(raw)
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"agent.yaml must be JSON-compatible YAML. Parse error: {exc}") from exc


def append_log(log_file: Path, payload: dict[str, Any]) -> None:
    log_file.parent.mkdir(parents=True, exist_ok=True)
    with log_file.open("a", encoding="utf-8") as fh:
        fh.write(json.dumps(payload, ensure_ascii=True) + "\n")


def _parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="PearTree Copilot Python Runner v2")
    parser.add_argument("--interactive", action="store_true", help="Print task execution details to stdout")
    parser.add_argument("--cycles", type=int, default=0, help="Stop after N cycles (0 means infinite)")
    parser.add_argument("--sleep", type=int, default=0, help="Override loop sleep seconds")
    return parser.parse_args()


def main() -> int:
    args = _parse_args()

    base = Path(__file__).resolve().parent.parent
    config_path = base / "config" / "agent.yaml"
    memory_path = Path(os.environ.get("AUTODEV_MEMORY_PATH") or str(base / "memory" / "context.json"))
    log_file = base / "logs" / "runner.log.jsonl"

    config = load_config(config_path)
    repo_value = Path(os.environ.get("AUTODEV_REPO_PATH") or str(config.get("repo_path") or "."))
    if not repo_value.is_absolute():
        repo_path = (base / repo_value).resolve()
    else:
        repo_path = repo_value.resolve()

    memory = load_memory(memory_path)
    memory.setdefault("history", [])
    memory.setdefault("meta", {})

    loop_seconds = int(args.sleep or config.get("loop_seconds", 120))
    max_cycles = int(args.cycles or os.environ.get("AUTODEV_MAX_CYCLES") or config.get("max_cycles", 0))

    log("AGENT V2 STARTED")
    log(f"repo={repo_path}")

    cycle = 0
    while True:
        cycle += 1
        started = datetime.now(timezone.utc).isoformat()

        tasks = planner.plan(memory, repo_path=repo_path, config=config)
        if not isinstance(tasks, list):
            tasks = []

        cycle_log: dict[str, Any] = {
            "cycle": cycle,
            "started_at": started,
            "tasks_planned": len(tasks),
            "tasks": [],
        }

        if not tasks:
            cycle_log["status"] = "idle"
            append_log(log_file, cycle_log)
            memory["meta"] = {
                "cycles": cycle,
                "last_status": "idle",
                "updated_at": datetime.now(timezone.utc).isoformat(),
            }
            save_memory(memory, memory_path)
            if max_cycles > 0 and cycle >= max_cycles:
                break
            time.sleep(loop_seconds)
            continue

        for task in tasks:
            task_name = str(task.get("name") or "unnamed")
            log(f"Executing: {task_name}")

            result = execute_task(task, repo_path=repo_path, config=config)
            decision = reviewer.review(task, result, memory=memory, config=config)

            if decision == "retry":
                retry_result = execute_task(task, repo_path=repo_path, config=config)
                retry_decision = reviewer.review(task, retry_result, memory=memory, config=config)
                result = retry_result
                decision = retry_decision

            history_entry = {
                "timestamp": datetime.now(timezone.utc).isoformat(),
                "task": task,
                "result": result,
                "decision": decision,
            }
            memory["history"].append(history_entry)
            cycle_log["tasks"].append(history_entry)

            if args.interactive:
                print(json.dumps(history_entry, indent=2, ensure_ascii=True))

        memory["meta"] = {
            "cycles": cycle,
            "last_status": "ok",
            "updated_at": datetime.now(timezone.utc).isoformat(),
        }

        # Keep memory bounded.
        history_limit = int(((config.get("memory") or {}).get("max_history", 500)))
        if history_limit > 0 and len(memory["history"]) > history_limit:
            memory["history"] = memory["history"][-history_limit:]

        save_memory(memory, memory_path)
        append_log(log_file, cycle_log)

        if max_cycles > 0 and cycle >= max_cycles:
            break

        time.sleep(loop_seconds)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
