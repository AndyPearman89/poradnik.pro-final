from __future__ import annotations

from datetime import datetime, timezone


def log(message: str) -> None:
    timestamp = datetime.now(timezone.utc).isoformat()
    print(f"[{timestamp}] {message}", flush=True)
