<?php

namespace AppBundle\Action\Task;

use AppBundle\Service\TaskManager;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Security;

class Assign
{
    use AssignTrait;

    public function __construct(
        protected Security $security,
        protected UserManagerInterface $userManager,
        protected AuthorizationCheckerInterface $authorizationChecker
    )
    {}

    public function __invoke($data, Request $request)
    {
        return $this->assign($data, $request->toArray(), $request);
    }
}
