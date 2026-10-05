FROM python:3.11-slim

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libgl1 \
        libglib2.0-0 \
        python3-tk \
        tk \
        v4l-utils \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY python/requirements.txt /tmp/requirements.txt
RUN pip install --no-cache-dir -r /tmp/requirements.txt

COPY python/ /app/
CMD ["python", "main.py"]
