## Prerequisites

Install [Docker](https://www.docker.com/) and [Minikube](https://minikube.sigs.k8s.io/docs/start/).

### Run minikube

```sh
minikube start
```

### 1. Build images

#### osrm
```sh
cd ./docker/osrm && docker build -t localhost:5000/osrm:1.0.0 . && cd ../..
```

#### php/symfony
```sh
docker build -t localhost:5000/php:1.0.0 . -f ./docker/php/Dockerfile --target frankenphp_prod
```


### 2. Publish images locally

(needs an extra config on macOS: https://minikube.sigs.k8s.io/docs/handbook/registry/#docker-on-macos ) run:

```sh
docker run --rm -it --network=host alpine ash -c "apk add socat && socat TCP-LISTEN:5000,reuseaddr,fork TCP:$(minikube ip):5000"
```

#### osrm
```sh
docker push localhost:5000/osrm:1.0.0
```

#### php/symfony
```sh
docker push localhost:5000/php:1.0.0
```

(if needed) update helm dependencies:

```sh
helm dependency update ./helm/php
```

### 3. Deploy using helm chart

(aka "install a helm release")

#### osrm
```sh
helm install coopcycle-osrm helm/osrm \
  --dependency-update \
  --set osrm.image.repository=localhost:5000/osrm \
  --set osrm.image.tag=1.0.0
```

#### php/symfony
```sh
helm install coopcycle-web helm/php \
  --dependency-update \
  --set php.image.repository=localhost:5000/php \
  --set php.image.tag=1.0.0
```

#### 3.1. Upgrade using helm chart

```sh
helm upgrade coopcycle-web helm/php \
  --dependency-update \
  --set php.image.repository=localhost:5000/php \
  --set php.image.tag=1.0.0
```

### 4. Setup port forwarding

see instructions in the terminal response

or

setup port forwarding via Lens IDE (https://k8slens.dev/)

### 5. Open the app in the browser

using the url from step 4


### Mics

### View Kubernetes dashboard:

```sh
minikube dashboard
```

or

Lens IDE (https://k8slens.dev/)

### (if needed to start from scratch) Uninstall a helm release:

#### osrm
```sh
helm uninstall coopcycle-osrm
```

#### php/symfony
```sh
helm uninstall coopcycle-web
```

### Stop minikube:

```sh
minikube stop
```
