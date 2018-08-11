<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Action\Utils\TokenStorageTrait;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\Common\Persistence\ManagerRegistry as DoctrineRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class Orders
{
    use TokenStorageTrait;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        DoctrineRegistry $doctrine,
        LoggerInterface $logger)
    {
        $this->tokenStorage = $tokenStorage;
        $this->doctrine = $doctrine;
        $this->logger = $logger;
    }

    public function __invoke(Request $request)
    {
        $id = $request->attributes->get('id');

        $date = $request->query->get('date', date('Y-m-d'));

        $restaurant = $this->doctrine->getRepository(Restaurant::class)->find($id);

        if (!$this->getUser()->ownsRestaurant($restaurant)) {
            throw new AccessDeniedHttpException(sprintf('Restaurant #%d does not belong to user "%s"',
                $restaurant->getId(), $this->getUser()->getUsername()));
        }

        $orderRepository = $this->doctrine->getRepository(Order::class);

        $qb = $orderRepository->createQueryBuilder('o');

        $qb->andWhere('o.restaurant = :restaurant');
        $qb->andWhere('o.state != :state_cart');
        $qb->andWhere('DATE(o.shippedAt) = :date');
        $qb->setParameter('restaurant', $restaurant);
        $qb->setParameter('state_cart', OrderInterface::STATE_CART);
        $qb->setParameter('date', $date);

        return $qb->getQuery()->getResult();
    }
}
