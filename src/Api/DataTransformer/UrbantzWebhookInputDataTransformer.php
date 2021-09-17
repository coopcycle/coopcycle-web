<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\UrbantzOrderInput;
use AppBundle\Api\Resource\UrbantzWebhook;

class UrbantzWebhookInputDataTransformer implements DataTransformerInterface
{
    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $webhook = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $webhook->tasks = $data->tasks;

        return $webhook;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof UrbantzWebhook) {
          return false;
        }

        return UrbantzWebhook::class === $to && UrbantzOrderInput::class === ($context['input']['class'] ?? null);
    }
}
