<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\OptinConsent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MyOptinConsents
{
    use TokenStorageTrait;

    protected $doctrine;

    public function __construct(TokenStorageInterface $tokenStorage, ManagerRegistry $doctrine)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
    }

    public function __invoke()
    {
        return $this->doctrine
            ->getRepository(OptinConsent::class)
            ->findByUser($this->getUser());
    }
}
