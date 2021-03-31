<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\Entity\Sylius\Order;
use AppBundle\Entity\Sylius\OrderRepository;
use AppBundle\Entity\Vendor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class Orders
{
    public function __construct(EntityManagerInterface $objectManager, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->objectManager = $objectManager;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function __invoke($data, Request $request)
    {
        $date = new \DateTime($request->get('date'));

        $start = clone $date;
        $end = clone $date;

        $start->setTime(0, 0, 0);
        $end->setTime(23, 59, 59);

        // We need to change the "_api_resource_class" attributes,
        // so that @context equals "/api/contexts/Order"
        $request->attributes->set('_api_resource_class', Order::class);

        // FIXME
        // Ideally, $authorizationChecker should be injected
        // into OrderRepository directly, but it seems impossible with Sylius dependency injection
        return $this->objectManager->getRepository(Order::class)
            ->findOrdersByRestaurantAndDateRange($data, $start, $end,
                $this->authorizationChecker->isGranted('ROLE_ADMIN'));
    }
}
