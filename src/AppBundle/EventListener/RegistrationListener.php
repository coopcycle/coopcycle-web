<?php

namespace AppBundle\EventListener;

use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\Event\FormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationListener implements EventSubscriberInterface
{
    private $router;

    public function __construct(UrlGeneratorInterface $router)
    {
        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            FOSUserEvents::REGISTRATION_SUCCESS => [
                ['onRegistrationSuccess', -10],
            ],
        ];
    }

    public function onRegistrationSuccess(FormEvent $event)
    {
        $form = $event->getForm();

        $accountType = $form->get('accountType')->getData();

        $roles = [];
        switch ($accountType) {
            case 'COURIER':
                $roles = ['ROLE_COURIER'];
                break;
            case 'STORE':
                $roles = ['ROLE_STORE'];
                break;
            case 'RESTAURANT':
                $roles = ['ROLE_RESTAURANT'];
                break;
        }

        $form->getData()->setRoles($roles);
    }
}
