<?php

namespace AppBundle\EventListener;

use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use AppBundle\Entity\OptinConsent;
use AppBundle\Enum\Optin;

class RegistrationListener implements EventSubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            NucleosProfileEvents::REGISTRATION_SUCCESS => [
                ['onRegistrationSuccess', -10],
            ],
        ];
    }

    public function onRegistrationSuccess(UserFormEvent $event)
    {
        $form = $event->getForm();
        $user = $event->getUser();

        foreach(Optin::values() as $optin) {
            if ($form->has($optin->getValue())) {
                $consent = new OptinConsent();

                $consent->setType($optin->getKey());
                $consent->setAsked(true);
                $consent->setAccepted($form->get($optin->getValue())->getData());

                $user->addOptinConsent($consent);
            }
        }
    }
}
