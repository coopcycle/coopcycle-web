<?php

namespace AppBundle\Service;

use AppBundle\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Util\TokenGeneratorInterface;
use Nucleos\UserBundle\Util\CanonicalizerInterface;

class InvitationManager
{
    public function __construct(
        TokenGeneratorInterface $tokenGenerator,
        CanonicalizerInterface $canonicalizer,
        EmailManager $emailManager,
        EntityManagerInterface $objectManager)
    {
        $this->tokenGenerator = $tokenGenerator;
        $this->canonicalizer = $canonicalizer;
        $this->emailManager = $emailManager;
        $this->objectManager = $objectManager;
    }

    public function send(Invitation $invitation): void
    {
        $invitation->setEmail($this->canonicalizer->canonicalize($invitation->getEmail()));
        $invitation->setCode($this->tokenGenerator->generateToken());

        $this->objectManager->persist($invitation);
        $this->objectManager->flush();

        // Send invitation email
        $this->emailManager->sendTo(
            $this->emailManager->createInvitationMessage($invitation),
            $invitation->getEmail()
        );

        $invitation->setSentAt(new \DateTime());
    }
}
