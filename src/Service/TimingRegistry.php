<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TimingRegistry
{
    public function __construct(
        EntityManagerInterface $entityManager,
        OrderFactory $orderFactory,
        OrderTimeHelper $orderTimeHelper,
        CacheInterface $projectCache)
    {
        $this->entityManager = $entityManager;
        $this->orderFactory = $orderFactory;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->projectCache = $projectCache;
    }

    public function getForObject($restaurant, $fulfillmentMethod = 'delivery')
    {
        $cacheKey = sprintf('restaurant.%d.%s.timing', $restaurant->getId(), $fulfillmentMethod);

        return $this->projectCache->get($cacheKey, function (ItemInterface $item)
            use ($restaurant, $fulfillmentMethod) {

            if (!$restaurant->isFulfillmentMethodEnabled($fulfillmentMethod)) {
                $item->expiresAfter(60 * 60);

                return [];
            }

            $item->expiresAfter(60 * 5);

            $cart = $this->orderFactory->createForRestaurant($restaurant);
            $cart->setFulfillmentMethod($fulfillmentMethod);

            $timeInfo = $this->orderTimeHelper->getTimeInfo($cart);

            return [
                'range' => $timeInfo['range'],
                'today' => $timeInfo['today'],
                'fast'  => $timeInfo['fast'],
                'diff'  => $timeInfo['diff'],
            ];
        });
    }

    public function getForId($id, $fulfillmentMethod = 'delivery')
    {
        $cacheKey = sprintf('restaurant.%d.%s.timing', $id, $fulfillmentMethod);

        return $this->projectCache->get($cacheKey, function (ItemInterface $item)
            use ($id, $fulfillmentMethod) {

            $restaurant = $this->entityManager->getRepository(LocalBusiness::class)->find($id);

            if (null === $restaurant || !$restaurant->isFulfillmentMethodEnabled($fulfillmentMethod)) {
                $item->expiresAfter(60 * 60);

                return [];
            }

            $item->expiresAfter(60 * 5);

            $cart = $this->orderFactory->createForRestaurant($restaurant);
            $cart->setFulfillmentMethod($fulfillmentMethod);

            $timeInfo = $this->orderTimeHelper->getTimeInfo($cart);

            return [
                'range' => $timeInfo['range'],
                'today' => $timeInfo['today'],
                'fast'  => $timeInfo['fast'],
                'diff'  => $timeInfo['diff'],
            ];
        });
    }
}
