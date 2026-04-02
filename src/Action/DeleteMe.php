<?php

namespace AppBundle\Action;

use Symfony\Bundle\SecurityBundle\Security;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

class DeleteMe
{
    public function __construct(
        private Security $security,
        private UserManagerInterface $userManager)
    {
    }

    public function __invoke()
    {
        $this->userManager->deleteUser($this->security->getUser());
    }
}
