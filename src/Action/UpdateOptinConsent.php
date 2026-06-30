<?php

namespace AppBundle\Action;

use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class UpdateOptinConsent
{
    public $userManager;

    public function __construct(private Security $security, UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    public function __invoke($data)
    {
        $user = $this->security->getUser();

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
