from __future__ import annotations

import os
import subprocess
from pathlib import Path
from typing import Any

if __package__ in (None, ""):
    import sys

    sys.path.append(str(Path(__file__).resolve().parent.parent))
    from agent.copilot import generate_code
else:
    from .copilot import generate_code


def _run(command: str, cwd: Path) -> dict[str, Any]:
    proc = subprocess.run(
        ["bash", "-lc", command],
        cwd=str(cwd),
        capture_output=True,
        text=True,
        check=False,
    )
    return {
        "exit_code": proc.returncode,
        "output": (proc.stdout or "") + (proc.stderr or ""),
        "command": command,
    }


def _shell_allowed(command: str, config: dict[str, Any]) -> bool:
    executor_cfg = config.get("executor") or {}
    if bool(executor_cfg.get("allow_shell", False)):
        return True

    prefixes = executor_cfg.get("allow_shell_prefixes") or [
        "git status",
        "php scripts/unit-test-services.php",
        "php scripts/unit-test-local-module-api.php",
    ]
    cmd = command.strip()
    for prefix in prefixes:
        if cmd.startswith(str(prefix)):
            return True
    return False


def execute_task(task: dict[str, Any], repo_path: Path, config: dict[str, Any]) -> dict[str, Any]:
    task_type = str(task.get("type") or "analyze").strip().lower()

    if task_type == "codegen":
        prompt = str(task.get("prompt") or "Improve system").strip()
        output_name = str(task.get("output_file") or "generated.py").strip()
        output_path = str((repo_path / output_name).resolve())
        model = str(((config.get("ai") or {}).get("model") or os.environ.get("AUTODEV_MODEL") or "gpt-5.3-codex"))
        code = generate_code(prompt, output_file=output_path, model=model)
        return {
            "exit_code": 0,
            "output": code,
            "command": f"generate_code:{output_name}",
        }

    if task_type in {"shell", "test", "analyze"}:
        command = str(task.get("command") or "").strip()
        if task_type == "test" and command == "":
            command = "pytest"
        if task_type == "analyze" and command == "":
            command = "git status --short"

        if command == "":
            return {
                "exit_code": 1,
                "output": "Missing command",
                "command": "",
            }

        if task_type == "shell" and not _shell_allowed(command, config):
            return {
                "exit_code": 126,
                "output": f"Shell command blocked by policy: {command}",
                "command": command,
            }

        return _run(command, repo_path)

    return {
        "exit_code": 127,
        "output": f"Unknown task type: {task_type}",
        "command": str(task.get("command") or ""),
    }
