from __future__ import annotations

import os
import re
import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Any

if __package__ in (None, ""):
    import sys

    sys.path.append(str(Path(__file__).resolve().parent.parent))
    from agent.copilot import ask_ai
else:
    from .copilot import ask_ai


@dataclass
class ValidationResult:
    ok: bool
    checks: list[dict[str, Any]]


def _run(command: list[str], cwd: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=str(cwd), text=True, capture_output=True, check=False)


def validate(repo_path: Path, changed_files: list[str], config: dict[str, Any]) -> ValidationResult:
    checks: list[dict[str, Any]] = []
    all_ok = True

    php_lint_enabled = bool(((config.get("validation") or {}).get("php_lint", True)))
    if php_lint_enabled:
        for rel in changed_files:
            if not rel.endswith(".php"):
                continue
            proc = _run(["php", "-l", rel], repo_path)
            ok = proc.returncode == 0
            checks.append(
                {
                    "name": f"php-lint:{rel}",
                    "ok": ok,
                    "stdout": proc.stdout,
                    "stderr": proc.stderr,
                }
            )
            all_ok = all_ok and ok

    sanity_commands = list(((config.get("validation") or {}).get("sanity_commands") or []))
    for command in sanity_commands:
        proc = _run(["bash", "-lc", str(command)], repo_path)
        ok = proc.returncode == 0
        checks.append(
            {
                "name": f"sanity:{command}",
                "ok": ok,
                "stdout": proc.stdout,
                "stderr": proc.stderr,
            }
        )
        all_ok = all_ok and ok

    return ValidationResult(ok=all_ok, checks=checks)


def review(task: dict[str, Any], result: dict[str, Any], memory: dict[str, Any] | None = None, config: dict[str, Any] | None = None) -> str:
    cfg = config or {}
    memory = memory or {}

    exit_code = int(result.get("exit_code", 0))
    output = str(result.get("output", ""))

    # Fast local heuristic first.
    if exit_code != 0:
        return "retry"
    lowered = output.lower()
    failure_markers = (
        "traceback (most recent call last)",
        "\noverall: fail",
        "\nfail:",
        "fatal error",
        "assertionerror",
        "command not found",
    )
    if any(marker in lowered for marker in failure_markers):
        return "improve"

    if re.search(r"(^|\n)error:\s", lowered) is not None:
        return "improve"

    reviewer_cfg = cfg.get("reviewer") or {}
    use_ai = bool(reviewer_cfg.get("use_ai", False))
    if not use_ai:
        return "success"

    model = str(((cfg.get("ai") or {}).get("model") or os.environ.get("AUTODEV_MODEL") or "gpt-5.3-codex"))
    history = memory.get("history", [])
    tail = history[-3:] if isinstance(history, list) else []

    prompt = (
        "You are a strict software reviewer. Return one word only: success, retry, or improve.\n"
        f"Task: {task}\n"
        f"Result: {result}\n"
        f"Recent history: {tail}\n"
    )
    decision = ask_ai(prompt, model=model).strip().lower()
    if decision not in {"success", "retry", "improve"}:
        return "success"
    return decision
