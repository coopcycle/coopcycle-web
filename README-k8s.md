## Introduction

Based on Symfony Docker:

https://symfony.com/blog/introducing-docker-support

https://github.com/dunglas/symfony-docker

Kubernetes: https://kubernetes.io/docs/concepts/architecture/

## Prerequisites

Install
- [Docker](https://www.docker.com/)
- [Minikube](https://minikube.sigs.k8s.io/docs/start/)
- [Helm](https://helm.sh/docs/intro/quickstart/#install-helm)
- [Skaffold](https://skaffold.dev/docs/install/)

### Run minikube

```sh
minikube start
```

### 1. Run skaffold

```sh
skaffold dev
```

### 2. Setup port forwarding

FIXME; setup a static port to avoid changing the port number on every reload

see instructions in the terminal response

or

setup port forwarding via Lens IDE (https://k8slens.dev/)

### 3. Open the app in the browser

using the url from step 4


### Mics

### View Kubernetes dashboard:

```sh
minikube dashboard
```

or

Lens IDE (https://k8slens.dev/)

### Stop minikube:

```sh
minikube stop
```
