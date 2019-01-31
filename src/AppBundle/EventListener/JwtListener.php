<?php

namespace AppBundle\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JwtListener
{
    /**
     * @param AuthenticationSuccessEvent $event
     */
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event)
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof UserInterface) {
            return;
        }

        $data['roles'] = $user->getRoles();
        $data['username'] = $user->getUsername();
        $data['email'] = $user->getEmail();
        $data['id'] = $user->getId();
        $data['enabled'] = $user->isEnabled();

        $event->setData($data);
    }
}
