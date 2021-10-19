<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Util\Inflector;
use AppBundle\Api\Resource\UrbantzWebhook;

final class UrbantzWebhookEventDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return UrbantzWebhook::class === $resourceClass;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?UrbantzWebhook
    {
        $id = Inflector::tableize($id);

        if (!UrbantzWebhook::isValidEvent($id)) {

            return null;
        }

        return new UrbantzWebhook($id);
    }
}
