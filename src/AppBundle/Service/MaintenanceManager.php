<?php

namespace AppBundle\Service;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

class MaintenanceManager
{
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function canBypass()
    {
        try {
            return $this->authorizationChecker->isGranted('ROLE_ADMIN')
                || $this->authorizationChecker->isGranted('ROLE_COURIER')
                || $this->authorizationChecker->isGranted('ROLE_RESTAURANT');
        } catch (AuthenticationCredentialsNotFoundException $e) {
            return false;
        }
    }
}
