from __future__ import annotations

import subprocess
from pathlib import Path


def _run(command: list[str], cwd: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(command, cwd=str(cwd), text=True, capture_output=True, check=False)


def stage_files(repo_path: Path, files: list[str]) -> None:
    if not files:
        return
    _run(["git", "add", "--"] + files, repo_path)


def commit(repo_path: Path, message: str) -> tuple[bool, str]:
    proc = _run(["git", "commit", "-m", message], repo_path)
    if proc.returncode != 0:
        return False, (proc.stderr or proc.stdout).strip()

    sha_proc = _run(["git", "rev-parse", "HEAD"], repo_path)
    return True, sha_proc.stdout.strip()


def push(repo_path: Path, remote: str, branch: str) -> tuple[bool, str]:
    proc = _run(["git", "push", remote, branch], repo_path)
    if proc.returncode != 0:
        return False, (proc.stderr or proc.stdout).strip()
    return True, proc.stdout.strip()


def changed_files(repo_path: Path) -> list[str]:
    proc = _run(["git", "status", "--porcelain"], repo_path)
    result: list[str] = []
    for row in proc.stdout.splitlines():
        row = row.strip()
        if not row:
            continue
        result.append(row[3:].strip())
    return result
