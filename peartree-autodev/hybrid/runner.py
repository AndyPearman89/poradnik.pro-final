from __future__ import annotations

import subprocess
import time
from pathlib import Path

from planner import get_next_task


def _run(cmd: list[str], cwd: Path) -> subprocess.CompletedProcess[str]:
    return subprocess.run(cmd, cwd=str(cwd), text=True, capture_output=True, check=False)


def _has_changes(repo_root: Path) -> bool:
    result = _run(["git", "status", "--porcelain"], repo_root)
    return bool(result.stdout.strip())


def _safe_commit_and_push(repo_root: Path, task: str) -> None:
    if not _has_changes(repo_root):
        print("No changes detected. Skipping commit/push.")
        return

    _run(["git", "add", "."], repo_root)

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
        input("Nacisnij ENTER po zakonczeniu... ")

        _safe_commit_and_push(repo_root, task)
        time.sleep(5)


if __name__ == "__main__":
    run()
