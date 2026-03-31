<?php

namespace AppBundle\Service;

use AppBundle\Entity\BusinessAccountInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationLinkProviderService
{
    private $urlGenerator;
    private $objectManager;

    public function __construct(
        private Security $security,
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $objectManager)
    {
        $this->urlGenerator = $urlGenerator;
        $this->objectManager = $objectManager;
    }

    public function getInvitationLink()
    {
        $user = $this->security->getUser();

        $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
            ->findOneBy([
                'businessAccount' => $user->getBusinessAccount(),
            ]);

        return $this->urlGenerator->generate('invitation_define_password', [
            'code' => $businessAccountInvitation->getInvitation()->getCode()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

}
