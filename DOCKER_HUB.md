Pushing images to Docker Hub
----------------------------

We have some pre-built images on [Docker Hub](https://hub.docker.com/u/coopcycle).

Read the [Docker Hub Quickstart](https://docs.docker.com/docker-hub/)

```
DOCKER_DEFAULT_PLATFORM=linux/amd64 docker compose build --no-cache php
docker push coopcycle/php:8.3
```

```
DOCKER_DEFAULT_PLATFORM=linux/amd64 docker compose build --no-cache webpack
docker push coopcycle/webpack:latest
```
