<?php

namespace AppBundle\Action\Restaurant;

use AppBundle\DataType\TsRange;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use AppBundle\Utils\Timing as TimingObj;
use AppBundle\Utils\TimeInfo;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class Timing
{
    public function __construct(
        OrderFactory $orderFactory,
        OrderTimeHelper $orderTimeHelper,
        CacheInterface $appCache)
    {
        $this->orderFactory = $orderFactory;
        $this->orderTimeHelper = $orderTimeHelper;
        $this->appCache = $appCache;
    }

    private function toTimeInfo($data): TimeInfo
    {
        $timeInfo = new TimeInfo();

        $range = new TsRange();
        $range->setLower(
            new \DateTime($data['range'][0])
        );
        $range->setUpper(
            new \DateTime($data['range'][1])
        );
        $timeInfo->range = $range;

        $timeInfo->today = $data['today'];
        $timeInfo->fast  = $data['fast'];
        $timeInfo->diff  = $data['diff'];

        return $timeInfo;
    }

    public function __invoke($data, Request $request)
    {
        $restaurant = $data;

        $deliveryCacheKey = sprintf('restaurant.%d.delivery.timing', $restaurant->getId());
        $collectionCacheKey = sprintf('restaurant.%d.collection.timing', $restaurant->getId());

        $result = [];

        if ($restaurant->isFulfillmentMethodEnabled('delivery')) {

            $result['delivery'] = $this->appCache->get($deliveryCacheKey, function (ItemInterface $item) use ($restaurant) {

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

            $result['collection'] = $this->appCache->get($collectionCacheKey, function (ItemInterface $item) use ($restaurant) {

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
