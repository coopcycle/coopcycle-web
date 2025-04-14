<?php

namespace AppBundle\Service;

use AppBundle\Entity\Invitation;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Util\TokenGenerator as TokenGeneratorInterface;
use Nucleos\UserBundle\Util\Canonicalizer as CanonicalizerInterface;

class InvitationManager
{
    public function __construct(
        private TokenGeneratorInterface $tokenGenerator,
        private CanonicalizerInterface $canonicalizer,
        private EmailManager $emailManager,
        private EntityManagerInterface $objectManager)
    {}

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
