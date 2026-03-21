from __future__ import annotations

import json
from pathlib import Path
from typing import Any


DEFAULT_MEMORY = Path(__file__).resolve().with_name("memory.json")


def load_memory(path: Path | None = None) -> dict[str, Any]:
    memory_file = path or DEFAULT_MEMORY
    if not memory_file.exists():
        return {"history": []}

    try:
        data = json.loads(memory_file.read_text(encoding="utf-8"))
    except Exception:
        return {"history": []}

    if not isinstance(data, dict):
        return {"history": []}

    history = data.get("history")
    if not isinstance(history, list):
        data["history"] = []
    return data


def save_memory(memory: dict[str, Any], path: Path | None = None) -> None:
    memory_file = path or DEFAULT_MEMORY
    memory_file.parent.mkdir(parents=True, exist_ok=True)
    memory_file.write_text(json.dumps(memory, indent=2, ensure_ascii=True) + "\n", encoding="utf-8")
