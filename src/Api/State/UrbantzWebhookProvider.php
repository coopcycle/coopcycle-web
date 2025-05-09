<?php

namespace AppBundle\Api\State;

use AppBundle\Api\Resource\UrbantzWebhook;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Util\Inflector;

final class UrbantzWebhookProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = [])
    {
        $id = Inflector::tableize($uriVariables['id']);

        if (!UrbantzWebhook::isValidEvent($id)) {

            return null;
        }

        return new UrbantzWebhook($id);
    }
}

