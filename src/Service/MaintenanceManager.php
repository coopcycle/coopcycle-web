<?php

namespace AppBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class MaintenanceManager
{
    /**
     * Endpoints that must keep working while maintenance mode is enabled.
     *
     * Zelty webhooks only notify us about things that happened on the Zelty
     * side for *existing* orders & catalogs (new orders are pushed by us to
     * Zelty, not the other way around). Answering them with a 503 does not
     * prevent anything, it only makes both systems drift apart, as Zelty does
     * not replay them.
     */
    private const ALLOWED_PATTERNS = [
        '#^/api/zelty/webhook/#',
    ];

    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    /**
     * Whether this request must go through, whoever is (or isn't) authenticated.
     */
    public function isAllowed(Request $request): bool
    {
        $path = rawurldecode($request->getPathInfo());

        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    public function canBypass()
    {
        try {
            return $this->authorizationChecker->isGranted('ROLE_ADMIN')
                || $this->authorizationChecker->isGranted('ROLE_COURIER')
                || $this->authorizationChecker->isGranted('ROLE_RESTAURANT')
                || $this->authorizationChecker->isGranted('ROLE_STORE');
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }
}
