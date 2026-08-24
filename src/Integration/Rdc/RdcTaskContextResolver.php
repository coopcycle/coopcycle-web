<?php

declare(strict_types=1);

namespace AppBundle\Integration\Rdc;

use AppBundle\Entity\Task;
use AppBundle\Integration\Rdc\Api\RdcClientFactory;

final class RdcTaskContextResolver
{
    public function __construct(private readonly RdcClientFactory $rdcClientFactory)
    {
    }

    public function resolve(Task $task): ?RdcContext
    {
        $delivery = $task->getDelivery();
        if (is_null($delivery) || is_null($delivery->getId())) {
            return null;
        }

        $store = $delivery->getStore();
        if (is_null($store)) {
            return null;
        }

        $connectionId = $store->getRdcConnectionId();
        if (is_null($connectionId)) {
            return null;
        }

        $client = $this->rdcClientFactory->create($connectionId);
        if (is_null($client)) {
            return null;
        }

        $pickup = $delivery->getPickup();
        if (is_null($pickup) || is_null($pickup->getId())) {
            return null;
        }

        return new RdcContext(
            client: $client,
            serviceId: sprintf('%s', $delivery->getId()),
            activityId: sprintf('%s.transport', $pickup->getId()),
        );
    }
}