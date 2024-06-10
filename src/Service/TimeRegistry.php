<?php

namespace AppBundle\Service;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Order\OrderFactory;
use AppBundle\Utils\OrderTimeHelper;
use Carbon\CarbonInterval;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class TimeRegistry
{
    const AVERAGE_PREPARATION_CACHE_KEY = 'avg_preparation';
    const AVERAGE_SHIPPING_CACHE_KEY = 'avg_shipping';

    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        private CacheInterface $appCache)
    {
        $this->entityManager = $entityManager;
    }

    public function getAveragePreparationTime(): int
    {
        $cacheKey = sprintf('%s|%s', 'TimeRegistry', self::AVERAGE_PREPARATION_CACHE_KEY);

        $result = $this->appCache->get($cacheKey, function (ItemInterface $item) {

            $item->expiresAfter(60 * 5);

            $sql = 'SELECT ROUND(AVG(EXTRACT(EPOCH FROM TO_CHAR(t.preparation_time::interval, \'HH24:MI\')::interval) / 60)) FROM sylius_order_timeline t JOIN sylius_order o ON t.order_id = o.id WHERE o.state = \'fulfilled\'';

            $stmt = $this->entityManager->getConnection()->prepare($sql);

            return $stmt->executeQuery()->fetchOne();
        });

        return (int) $result;
    }

    public function getAverageShippingTime(): int
    {
        $cacheKey = sprintf('%s|%s', 'TimeRegistry', self::AVERAGE_SHIPPING_CACHE_KEY);

        $result = $this->appCache->get($cacheKey, function (ItemInterface $item) {

            $item->expiresAfter(60 * 5);

            $sql = 'SELECT ROUND(AVG(tc.duration)) FROM task_collection tc JOIN delivery d ON tc.id = d.id JOIN sylius_order o ON d.order_id = o.id WHERE tc.type = \'delivery\' and o.state = \'fulfilled\'';

            $stmt = $this->entityManager->getConnection()->prepare($sql);
            $result = $stmt->executeQuery()->fetchOne();

            $cascade = CarbonInterval::seconds((int) $result)
                ->cascade()
                ->toArray();

            return $cascade['minutes'];
        });

        return (int) $result;
    }
}
