<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\DataType\TsRange;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\Timing as TimingObj;
use AppBundle\Utils\TimeInfo;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Timing
{
    public function __construct(
        OrderFactory $orderFactory,
        OrderTimeHelper $orderTimeHelper,
        CacheInterface $projectCache)
    {
        $this->orderFactory = $orderFactory;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->projectCache = $projectCache;
    }

    private function toTimeInfo($data): TimeInfo
    {
        $timeInfo = new TimeInfo();

        // FIXME
        // Refactor this crap
        // https://github.com/coopcycle/coopcycle-web/issues/2213

        $rangeAsArray = isset($data['range']) && is_array($data['range']) ?
            $data['range'] : [ null, null ];

        $range = new TsRange();
        $range->setLower(
            new \DateTime($rangeAsArray[0])
        );
        $range->setUpper(
            new \DateTime($rangeAsArray[1])
        );
        $timeInfo->range = $range;

        $timeInfo->today = $data['today'] ?? false;
        $timeInfo->fast  = $data['fast'] ?? false;
        $timeInfo->diff  = $data['diff'];

        return $timeInfo;
    }

    public function __invoke($data)
    {
        $restaurant = $data;

        $deliveryCacheKey = sprintf('restaurant.%d.delivery.timing', $restaurant->getId());
        $collectionCacheKey = sprintf('restaurant.%d.collection.timing', $restaurant->getId());

        $result = [];

        if ($restaurant->isFulfillmentMethodEnabled('delivery')) {

            $result['delivery'] = $this->projectCache->get($deliveryCacheKey, function (ItemInterface $item) use ($restaurant) {

                $item->expiresAfter(60 * 5);

                $cart = $this->orderFactory->createForRestaurant($restaurant);
                $cart->setTakeaway(false);

                $timeInfo = $this->orderTimeHelper->getTimeInfo($cart);

                return [
                    'range' => $timeInfo['range'],
                    'today' => $timeInfo['today'],
                    'fast'  => $timeInfo['fast'],
                    'diff'  => $timeInfo['diff'],
                ];
            });
        }

        if ($restaurant->isFulfillmentMethodEnabled('collection')) {

            $result['collection'] = $this->projectCache->get($collectionCacheKey, function (ItemInterface $item) use ($restaurant) {

                $item->expiresAfter(60 * 5);

                $cart = $this->orderFactory->createForRestaurant($restaurant);
                $cart->setTakeaway(true);

                $timeInfo = $this->orderTimeHelper->getTimeInfo($cart);

                return [
                    'range' => $timeInfo['range'],
                    'today' => $timeInfo['today'],
                    'fast'  => $timeInfo['fast'],
                    'diff'  => $timeInfo['diff'],
                ];
            });
        }

        $timing = new TimingObj();

        if (isset($result['delivery'])) {
            $timing->delivery = $this->toTimeInfo($result['delivery']);
        }

        if (isset($result['collection'])) {
            $timing->collection = $this->toTimeInfo($result['collection']);
        }

        return $timing;
    }
}
