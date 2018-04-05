<?php

namespace AppBundle\Action;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Sylius\Order;
use Doctrine\Common\Persistence\ManagerRegistry;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
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

    /**
     * @Route(
     *     name="my_orders",
     *     path="/me/orders",
     *     defaults={
     *         "_api_resource_class"=Order::class,
     *         "_api_collection_operation_name"="my_orders"
     *     }
     * )
     * @Method("GET")
     */
    public function __invoke($data)
    {
        return $this->doctrine
            ->getRepository(Order::class)
            ->findBy(['customer' => $this->getUser()], ['createdAt' => 'DESC'], 30);
    }
}
