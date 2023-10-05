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
        UserManagerInterface $userManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userManager = $userManager;
    }

    public function __invoke()
    {
        $this->userManager->deleteUser($this->getUser());
    }
}
