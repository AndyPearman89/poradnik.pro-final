from __future__ import annotations

import os


def _offline_fallback(prompt: str) -> str:
    lowered = prompt.lower()
    if "return only strict json array" in lowered:
        return (
            "["
            "{\"name\":\"Repository status snapshot\",\"type\":\"analyze\",\"command\":\"git status --short\"},"
            "{\"name\":\"Run service unit tests\",\"type\":\"test\",\"command\":\"php scripts/unit-test-services.php\"},"
            "{\"name\":\"Run local module API tests\",\"type\":\"test\",\"command\":\"php scripts/unit-test-local-module-api.php\"}"
            "]"
        )
    return "success"


def ask_ai(prompt: str, model: str = "gpt-5.3-codex") -> str:
    api_key = os.environ.get("OPENAI_API_KEY", "").strip()
    if api_key == "":
        return _offline_fallback(prompt)

    try:
        from openai import OpenAI

        client = OpenAI(api_key=api_key)
        response = client.chat.completions.create(
            model=model,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.2,
        )
        content = response.choices[0].message.content
        return content if isinstance(content, str) else str(content)
    except Exception:
        return _offline_fallback(prompt)


def generate_code(prompt: str, output_file: str = "generated.py", model: str = "gpt-5.3-codex") -> str:
    code = ask_ai(prompt, model=model)
    with open(output_file, "w", encoding="utf-8") as handle:
        handle.write(code)
    return code
