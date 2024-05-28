<?php

namespace AppBundle\Service;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\BusinessAccountInvitation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class InvitationLinkProviderService
{
    use TokenStorageTrait;

    private $urlGenerator;
    private $objectManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        UrlGeneratorInterface $urlGenerator,
        EntityManagerInterface $objectManager)
    {
        $this->tokenStorage = $tokenStorage;
        $this->urlGenerator = $urlGenerator;
        $this->objectManager = $objectManager;
    }

    public function getInvitationLink()
    {
        $user = $this->getUser();

        $businessAccountInvitation = $this->objectManager->getRepository(BusinessAccountInvitation::class)
            ->findOneBy([
                'businessAccount' => $user->getBusinessAccount(),
            ]);

        return $this->urlGenerator->generate('invitation_define_password', [
            'code' => $businessAccountInvitation->getInvitation()->getCode()
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

}
