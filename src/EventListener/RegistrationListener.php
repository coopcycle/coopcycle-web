<?php

namespace AppBundle\EventListener;

use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\UserBundle\Event\FormEvent;
use AppBundle\Entity\OptinConsent;
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
        $user = $form->getData();

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

            $user->setRoles($roles);
        }

        if ($form->has('newsletterOptin')) {

            $consent = new OptinConsent();
            $consent->setType('newsletter');

            $user->addOptinConsent($consent);
        }

        if ($form->has('marketingOptin')) {

            $consent = new OptinConsent();
            $consent->setType('marketing');

            $user->addOptinConsent($consent);
        }
    }
}
