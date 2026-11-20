<?php

namespace AppBundle\EventListener;

use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use AppBundle\Entity\OptinConsent;
use AppBundle\Enum\Optin;

class RegistrationListener implements EventSubscriberInterface
{
    public function __construct(private readonly bool $confirmationEnabled)
    {
    }

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

        // Nucleos\ProfileBundle\EventListener\EmailConfirmationListener is the
        // only vendor code that ever calls setEnabled(), and it's only
        // registered when confirmation is enabled (it then explicitly
        // disables the account until the confirmation link is clicked). When
        // confirmation is disabled, nothing enables the account otherwise --
        // Nucleos\UserBundle\Model\User::$enabled defaults to false -- so the
        // web registration flow left every new account disabled, unable to
        // log in. AppBundle\Action\Register (the app/API registration flow)
        // already handles this explicitly; this is its web-flow equivalent.
        if (!$this->confirmationEnabled) {
            $user->setEnabled(true);
        }
    }
}
