<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Sylius\Order;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MyOrders
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
            ->getRepository(Order::class)
            ->findByUser($this->getUser());
    }
}
