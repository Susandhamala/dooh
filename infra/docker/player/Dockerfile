FROM python:3.13-slim
WORKDIR /app
COPY player/pyproject.toml ./
COPY player/dooh_player ./dooh_player
RUN pip install --no-cache-dir .
RUN useradd --system player && mkdir -p /var/lib/dooh/config && chown -R player /var/lib/dooh
USER player
# Emulated screen: key, certificate and config live in the data volume.
ENV DOOH_CONFIG_DIR=/var/lib/dooh/config DOOH_DATA_DIR=/var/lib/dooh
ENTRYPOINT ["dooh-player"]
CMD ["run"]
