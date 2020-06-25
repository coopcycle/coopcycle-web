<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Api\Dto\PricingRuleEvaluate;
use AppBundle\Entity\Delivery\PricingRule;

final class PricingRuleEvaluateInputDataTransformer extends DeliveryInputDataTransformer
{
    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $delivery = parent::transform($data, $to, $context);

        $output = new PricingRuleEvaluate();

        $output->pricingRule = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $output->delivery = $delivery;

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof PricingRule) {
          return false;
        }

        return PricingRule::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
