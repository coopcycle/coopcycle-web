<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;

class DeleteMe
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        private UserManagerInterface $userManager)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function __invoke()
    {
        $this->userManager->deleteUser($this->getUser());
    }
}
