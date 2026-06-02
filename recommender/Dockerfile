FROM python:3.12-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY recommender/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY recommender/ .

RUN mkdir -p /models

EXPOSE 8000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
