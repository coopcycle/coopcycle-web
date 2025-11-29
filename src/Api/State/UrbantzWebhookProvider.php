<?php

namespace AppBundle\Api\State;

use AppBundle\Api\Resource\UrbantzWebhook;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\Util\Inflector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class UrbantzWebhookProvider implements ProviderInterface
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $id = Inflector::tableize($uriVariables['id']);

        if (!UrbantzWebhook::isValidEvent($id)) {
            // We throw an exception instead of returning null,
            // because otherwise the process would continue
            throw new NotFoundHttpException();
        }

        return new UrbantzWebhook($id);
    }
}

