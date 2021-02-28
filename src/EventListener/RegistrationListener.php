<?php

namespace AppBundle\EventListener;

use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\UserBundle\Event\FormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RegistrationListener implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            NucleosProfileEvents::REGISTRATION_SUCCESS => [
                ['onRegistrationSuccess', -10],
            ],
        ];
    }

    public function onRegistrationSuccess(FormEvent $event)
    {
        $form = $event->getForm();

        if ($form->has('accountType')) {

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
}
