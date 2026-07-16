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

```
COMPOSE_PROFILES=disabled DOCKER_DEFAULT_PLATFORM=linux/amd64 docker compose build --no-cache recommender
docker push coopcycle/recommender:latest
```

Note: `recommender` sits behind `profiles: [disabled]` in `docker-compose.override.yml` (so it doesn't start
for every dev by default). Naming it explicitly on the CLI still builds it, but Compose only forwards
`DOCKER_DEFAULT_PLATFORM` into the generated build plan for services in the *active* profile set — for a
service outside that set it's silently dropped, producing an image matching the host's native architecture
instead of `linux/amd64` (confirmed via `docker compose build --print recommender`, and via
`docker inspect --format '{{.Architecture}}'` on the resulting image). This is what caused an arm64 image
built on Apple Silicon to fail with `exec format error` on an amd64 production host. `COMPOSE_PROFILES=disabled`
activates the profile so the platform override is honored, same as the `php`/`webpack` commands above.
