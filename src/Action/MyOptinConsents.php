<?php

namespace AppBundle\Action;

use AppBundle\Entity\OptinConsent;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Attribute\Route;

class MyOptinConsents
{
    protected $doctrine;

    public function __construct(private Security $security, ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function __invoke()
    {
        return $this->doctrine
            ->getRepository(OptinConsent::class)
            ->findByUser($this->security->getUser());
    }
}
