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
    public function __construct(
        EntityManagerInterface $entityManager,
        CacheInterface $projectCache)
    {
        $this->entityManager = $entityManager;
        $this->projectCache = $projectCache;
    }

    public function getAveragePreparationTime(): int
    {
        $sql = 'SELECT ROUND(AVG(EXTRACT(EPOCH FROM TO_CHAR(t.preparation_time::interval, \'HH24:MI\')::interval) / 60)) FROM sylius_order_timeline t JOIN sylius_order o ON t.order_id = o.id WHERE o.state = \'fulfilled\'';

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery()->fetchColumn();

        // TODO Cache

        return (int) $result;
    }

    public function getAverageShippingTime(): int
    {
        $sql = 'SELECT ROUND(AVG(tc.duration)) FROM task_collection tc JOIN delivery d ON tc.id = d.id JOIN sylius_order o ON d.order_id = o.id WHERE tc.type = \'delivery\' and o.state = \'fulfilled\'';

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $result = $stmt->executeQuery()->fetchColumn();

        $cascade = CarbonInterval::seconds((int) $result)
            ->cascade()
            ->toArray();

        // TODO Cache

        return (int) $cascade['minutes'];
    }
}
