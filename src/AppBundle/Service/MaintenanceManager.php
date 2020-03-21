<?php

namespace AppBundle\Service;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MaintenanceManager
{
    private $authorizationChecker;

    public function __construct(AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->authorizationChecker = $authorizationChecker;
    }

    public function canBypass()
    {
        return $this->authorizationChecker->isGranted('ROLE_ADMIN')
            || $this->authorizationChecker->isGranted('ROLE_COURIER');
    }
}
