FROM python:3.13-slim
WORKDIR /app
COPY player/pyproject.toml ./
COPY player/dooh_player ./dooh_player
RUN pip install --no-cache-dir .
RUN useradd --system player && mkdir -p /var/lib/dooh && chown player /var/lib/dooh
USER player
ENTRYPOINT ["dooh-player"]
