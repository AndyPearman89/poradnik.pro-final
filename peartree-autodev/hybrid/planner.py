from __future__ import annotations

from pathlib import Path


def _parse_work_queue(queue_file: Path) -> list[str]:
    """Parse autodev-work-queue.md and return list of OPEN tasks."""
    if not queue_file.exists():
        return []

    content = queue_file.read_text(encoding="utf-8")
    open_tasks = []

    for line in content.splitlines():
        line = line.strip()
        if line.startswith("- OPEN:"):
            # Extract task description after "- OPEN: "
            task = line[len("- OPEN:"):].strip()
            open_tasks.append(task)

    return open_tasks


def _parse_tasklist(tasklist_file: Path) -> list[str]:
    """Parse final-project-tasklist.md and return list of OPEN/WIP tasks."""
    if not tasklist_file.exists():
        return []

    content = tasklist_file.read_text(encoding="utf-8")
    tasks = []

    for line in content.splitlines():
        line = line.strip()
        # Look for lines like "- [OPEN] TASK-XXX - description"
        if "- [OPEN]" in line or "- [WIP]" in line:
            # Extract the full task description
            task = line.split("]", 1)[1].strip() if "]" in line else line
            tasks.append(task)

    return tasks


def get_next_task(repo_root: Path, src_root: Path) -> str:
    """Return the next hybrid task based on work queue and tasklist."""
    # Check autodev-work-queue.md first
    queue_file = repo_root / "docs" / "implementation" / "autodev-work-queue.md"
    open_tasks = _parse_work_queue(queue_file)

    if open_tasks:
        return open_tasks[0]

    # Check final-project-tasklist.md
    tasklist_file = repo_root / "docs" / "implementation" / "final-project-tasklist.md"
    tasklist_tasks = _parse_tasklist(tasklist_file)

    if tasklist_tasks:
        return tasklist_tasks[0]

    # Fallback: check if there are any improvements to make
    # Look for TODO/FIXME comments in PHP files
    poradnik_theme = repo_root / "poradnik.pro"
    if poradnik_theme.exists():
        return "Review and address TODO/FIXME comments in poradnik.pro theme"

    return "All tasks completed! Review documentation and prepare for release."
