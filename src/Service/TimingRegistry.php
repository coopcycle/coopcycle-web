<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TimingRegistry
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderFactory $orderFactory,
        private OrderTimeHelper $orderTimeHelper,
        private CacheInterface $projectCache)
    {}

    public function getAllFulfilmentMethodsForObject($restaurant)
    {
        $cacheKey = sprintf('restaurant.%d.%s.timing', $restaurant->getId(), 'all');

        return $this->projectCache->get($cacheKey, function (ItemInterface $item)
        use ($restaurant) {
            $item->expiresAfter(60 * 5);

            return $this->calcTimingInfoForAllFulfilmentMethods($restaurant);
        });
    }

    public function getAllFulfilmentMethodsForId($id)
    {
        $cacheKey = sprintf('restaurant.%d.%s.timing', $id, 'all');

        return $this->projectCache->get($cacheKey, function (ItemInterface $item)
        use ($id) {

            $restaurant = $this->entityManager->getRepository(LocalBusiness::class)->find($id);

            if (null === $restaurant) {
                $item->expiresAfter(60 * 60);

                return [];
            }

            $item->expiresAfter(60 * 5);

            return $this->calcTimingInfoForAllFulfilmentMethods($restaurant);
        });
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

            return $this->calcTimingInfoForFulfilmentMethod($restaurant, $fulfillmentMethod);
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

            return $this->calcTimingInfoForFulfilmentMethod($restaurant, $fulfillmentMethod);
        });
    }

    private function isPossible($timingInfo): bool
    {
        return array_key_exists('range', $timingInfo) && !is_null($timingInfo['range']) && count($timingInfo['range']) > 0;
    }

    private function calcTimingInfoForAllFulfilmentMethods($restaurant) {
        $delivery = $this->getForObject($restaurant, 'delivery');
        $collection = $this->getForObject($restaurant, 'collection');

        $methods = [];

        if ($this->isPossible($delivery)) {
            $methods['delivery'] = $delivery;
        }
        if ($this->isPossible($collection)) {
            $methods['collection'] = $collection;
        }

        // sort fulfilment methods based on the priority
        // at the moment: the earliest possible time slot
        uasort($methods, function($a, $b) {
            $aStart = new DateTime($a['range'][0]);
            $bStart = new DateTime($b['range'][0]);

            if ($aStart == $bStart) {
                return 0;
            }
            return ($aStart < $bStart) ? -1 : 1;
        });
        $methods['firstChoiceKey'] = array_key_first($methods);

        return $methods;
    }


    private function calcTimingInfoForFulfilmentMethod($restaurant, $fulfillmentMethod)
    {
        $cart = $this->orderFactory->createForRestaurant($restaurant);
        $cart->setFulfillmentMethod($fulfillmentMethod);

        $timeInfo = $this->orderTimeHelper->getTimeInfo($cart);

        return [
            'range' => $timeInfo['range'],
            'today' => $timeInfo['today'],
            'fast'  => $timeInfo['fast'],
            'diff'  => $timeInfo['diff'],
        ];
    }
}
