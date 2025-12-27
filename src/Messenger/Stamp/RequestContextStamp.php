<?php

namespace AppBundle\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

class RequestContextStamp implements StampInterface
{
    public function __construct(
        private readonly ?string $requestId,
        private readonly ?string $route,
        private readonly ?string $controller,
        private readonly ?string $clientIp,
        private readonly ?string $userAgent,
        private readonly ?string $username,
        private readonly array $roles
    ) {
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function getController(): ?string
    {
        return $this->controller;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}

