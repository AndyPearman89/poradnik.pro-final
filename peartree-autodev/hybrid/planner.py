from __future__ import annotations

from pathlib import Path


def get_next_task(repo_root: Path, src_root: Path) -> str:
    """Return the next hybrid task based on project structure."""
    leads = src_root / "Leads"
    booking = src_root / "Booking"

    if not leads.exists():
        return "Create Leads module (DDD + REST API)"

    if not booking.exists():
        return "Create Booking Engine module"

    return "Refactor Listings module and improve performance"
