<?php

namespace AppBundle\Action\Order;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\Hub;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Entity\Vendor;
use AppBundle\Sylius\Order\OrderInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Component\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SearchAdhoc
{

    public function __construct(
        ManagerRegistry $doctrine,
        OrderRepositoryInterface $orderRepository,
        LocalBusinessRepository $localBusinessRepository,
        IriConverterInterface $iriConverter)
    {
        $this->doctrine = $doctrine;
        $this->orderRepository = $orderRepository;
        $this->localBusinessRepository = $localBusinessRepository;
        $this->iriConverter = $iriConverter;
    }

    public function __invoke(Request $request)
    {
        $restaurant = $this->iriConverter->getItemFromIri($request->query->get('restaurant'));

        if (!$restaurant) {
            throw new BadRequestException("Restaurant not found");
        }

        $hub = $this->iriConverter->getItemFromIri($request->query->get('hub'));

        if (!$hub) {
            throw new BadRequestException("Hub not found");
        }

        $restaurantInHub = $this->checkIfRestaurantIsInHub($restaurant, $hub);

        if (!$restaurantInHub) {
            throw new BadRequestException("Restaurant is not in hub");
        }

        $qb = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.number = :number')
            ->andWhere('o.state = :state')
            ->setParameter('number', $request->query->get('orderNumber'))
            ->setParameter('state', OrderInterface::STATE_CART);

        $qb = $this->addRestaurantOrHubVendorClause($qb, 'o', $restaurant, $hub);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (null === $result) {
            throw new NotFoundHttpException();
        }

        return $result;
    }

    private function checkIfRestaurantIsInHub(LocalBusiness $restaurant, Hub $hub)
    {
        $qb = $this->localBusinessRepository
            ->createQueryBuilder('r')
            ->innerJoin(Hub::class, 'h', Join::WITH, 'r.hub = h.id')
            ->andWhere('r = :restaurant')
            ->andWhere('h = :hub')
            ->setParameter('restaurant', $restaurant)
            ->setParameter('hub', $hub);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function addRestaurantOrHubVendorClause(QueryBuilder $qb, $alias, LocalBusiness $restaurant, Hub $hub)
    {
        return $qb
            ->join(Vendor::class, 'v', Join::WITH, sprintf('%s.vendor = v.id', $alias))
            ->andWhere('v.hub = :hub OR v.restaurant in (:hub_restaurants)')
            ->setParameter('hub_restaurants', $hub->getRestaurants())
            ->setParameter('hub', $hub);
    }
}
