FROM python:3.12-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY recommender/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# The prophet linux/aarch64 wheel ships the compiled model and cmdstan binaries
# but omits cmdstan's makefile, which cmdstanpy's path validation requires.
# The makefile itself is never used (the model is prebuilt), a stub suffices.
RUN find /usr/local/lib -type d -path '*/prophet/stan_model/cmdstan-*' -exec touch '{}/makefile' \;

COPY recommender/ .

RUN mkdir -p /models

EXPOSE 8000

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]
