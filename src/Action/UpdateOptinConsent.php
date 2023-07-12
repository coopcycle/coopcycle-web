<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\OptinConsent;
use Doctrine\Persistence\ManagerRegistry;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UpdateOptinConsent
{
    use TokenStorageTrait;

    public function __construct(TokenStorageInterface $tokenStorage, UserManagerInterface $userManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->userManager = $userManager;
    }

    public function __invoke($data)
    {
        $user = $this->getUser();

        foreach($user->getOptinConsents() as $userOptinConsent) {
            if ($userOptinConsent->getType() === $data->getType()) {
                $userOptinConsent->setAccepted($data->getAccepted());
                $userOptinConsent->setAsked($data->getAsked());
            }
        }

        $this->userManager->updateUser($user);

        return $user->getOptinConsents();
    }
}
