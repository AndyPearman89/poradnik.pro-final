from __future__ import annotations

import os
import subprocess
import time
from pathlib import Path

from planner import get_next_task


def _run(cmd: list[str], cwd: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(cwd), text=True, capture_output=True, check=False)


def _has_changes(repo_root: Path) -> bool:
    result = _run(["git", "status", "--porcelain"], repo_root)
    return bool(result.stdout.strip())


def _list_changed_files(repo_root: Path) -> list[str]:
    result = _run(["git", "status", "--porcelain"], repo_root)
    if result.returncode != 0:
        return []

    changed: list[str] = []
    for line in result.stdout.splitlines():
        if not line.strip():
            continue
        # Format: XY <path>
        raw_path = line[3:].strip()
        # Handle rename format: old -> new
        if " -> " in raw_path:
            raw_path = raw_path.split(" -> ", 1)[1].strip()
        changed.append(raw_path)

    return changed


def _is_allowed_for_stage(path: str) -> bool:
    denied_prefixes = (
        "peartree-autodev/memory/",
        "peartree-autodev/logs/",
    )
    denied_contains = (
        "__pycache__/",
    )
    denied_suffixes = (
        ".pyc",
    )

    if path.startswith(denied_prefixes):
        return False
    if any(marker in path for marker in denied_contains):
        return False
    if path.endswith(denied_suffixes):
        return False
    return True


def _stage_allowed_changes(repo_root: Path) -> int:
    changed = _list_changed_files(repo_root)
    allowed = [path for path in changed if _is_allowed_for_stage(path)]

    for rel_path in allowed:
        _run(["git", "add", "--", rel_path], repo_root)

    return len(allowed)


def _safe_commit_and_push(repo_root: Path, task: str) -> None:
    if not _has_changes(repo_root):
        print("No changes detected. Skipping commit/push.")
        return

    staged_candidates = _stage_allowed_changes(repo_root)
    if staged_candidates == 0:
        print("No allowed files to stage. Skipping commit/push.")
        return

    # Skip commit if nothing ended up staged.
    staged_check = _run(["git", "diff", "--cached", "--quiet"], repo_root)
    if staged_check.returncode == 0:
        print("No staged changes after git add. Skipping commit/push.")
        return

    commit = _run(["git", "commit", "-m", f"feat: {task}"], repo_root)
    if commit.returncode != 0:
        print("Commit failed:")
        print(commit.stdout)
        print(commit.stderr)
        return

    push = _run(["git", "push"], repo_root)
    if push.returncode != 0:
        print("Push failed:")
        print(push.stdout)
        print(push.stderr)
        return

    print("Commit + push: OK")


def run() -> None:
    repo_root = Path(__file__).resolve().parents[2]
    src_root = repo_root / "src"
    task_file = Path(__file__).resolve().parent / "TASK.md"

    while True:
        print("=== AUTODEV HYBRID LOOP ===")

        task = get_next_task(repo_root, src_root)
        print(f"TASK: {task}")

        task_file.write_text(task + "\n", encoding="utf-8")

        print("➡️ Otworz TASK.md i wykonaj task w Copilot")
        try:
            input("Nacisnij ENTER po zakonczeniu... ")
        except EOFError:
            print("Brak interaktywnego stdin. Koncze petle hybrid.")
            break

        _safe_commit_and_push(repo_root, task)

        if os.environ.get("HYBRID_RUN_ONCE") == "1":
            print("HYBRID_RUN_ONCE=1, koncze po jednym cyklu.")
            break

        time.sleep(5)


if __name__ == "__main__":
    run()
