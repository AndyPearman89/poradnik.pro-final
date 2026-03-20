from __future__ import annotations

import subprocess
from dataclasses import dataclass
from pathlib import Path
from typing import Any


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
