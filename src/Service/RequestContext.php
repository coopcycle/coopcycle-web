<?php

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Stores request context data such as request_id, route, controller etc.
 *
 * In web context: reads data dynamically from RequestStack and TokenStorage
 * In worker context: pre-fills data from RequestContextStamp
 */
class RequestContext
{
    private ?string $requestId = null;
    private ?string $route = null;
    private ?string $controller = null;
    private ?string $clientIp = null;
    private ?string $userAgent = null;
    private ?string $username = null;
    private ?array $roles = null;
    private bool $prefilledFromStamp = false;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage
    ) {
    }

    public function getRequestId(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->requestId;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->headers->has('X-Request-ID')) {
            return $request->headers->get('X-Request-ID');
        }

        return null;
    }

    public function getRoute(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->route;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->attributes->get('_route');
        }

        return null;
    }

    public function getController(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->controller;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->attributes->get('_controller');
        }

        return null;
    }

    public function getClientIp(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->clientIp;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            return $request->getClientIp();
        }

        return null;
    }

    public function getUserAgent(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->userAgent;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->headers->has('User-Agent')) {
            return $request->headers->get('User-Agent');
        }

        return null;
    }

    public function getUsername(): ?string
    {
        if ($this->prefilledFromStamp) {
            return $this->username;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!is_object($user)) {
            return null;
        }

        return $user->getUsername();
    }

    public function getRoles(): array
    {
        if ($this->prefilledFromStamp) {
            return $this->roles ?? [];
        }

        return $this->tokenStorage->getToken()?->getRoleNames() ?? [];
    }

    /**
     * Pre-fill context from stamp (worker context)
     */
    public function setFromStamp(
        ?string $requestId,
        ?string $route,
        ?string $controller,
        ?string $clientIp,
        ?string $userAgent,
        ?string $username,
        array $roles
    ): void {
        $this->requestId = $requestId;
        $this->route = $route;
        $this->controller = $controller;
        $this->clientIp = $clientIp;
        $this->userAgent = $userAgent;
        $this->username = $username;
        $this->roles = $roles;
        $this->prefilledFromStamp = true;
    }

    /**
     * Clear pre-filled data (after message processing)
     */
    public function clear(): void
    {
        $this->requestId = null;
        $this->route = null;
        $this->controller = null;
        $this->clientIp = null;
        $this->userAgent = null;
        $this->username = null;
        $this->roles = null;
        $this->prefilledFromStamp = false;
    }
}

