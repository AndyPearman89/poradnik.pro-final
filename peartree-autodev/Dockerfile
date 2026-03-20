FROM python:3.12-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends git bash php-cli ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY peartree-autodev /app/peartree-autodev

ENV AUTODEV_REPO_PATH=/workspace

CMD ["python", "peartree-autodev/agent/runner.py"]
