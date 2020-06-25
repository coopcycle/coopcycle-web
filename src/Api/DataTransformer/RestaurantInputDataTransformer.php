<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use AppBundle\Entity\LocalBusiness;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;

class RestaurantInputDataTransformer implements DataTransformerInterface
{
    public function __construct(IriConverterInterface $iriConverter)
    {
        $this->iriConverter = $iriConverter;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($data, string $to, array $context = [])
    {
        $restaurant = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];

        if (!empty($data->hasMenu) && is_string($data->hasMenu)) {
            $menu = $this->iriConverter->getItemFromIri($data->hasMenu);
            $restaurant->setMenuTaxon($menu);
        }

        if (!empty($data->state)) {
            $restaurant->setState($data->state);
        }

        return $restaurant;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof LocalBusiness) {
          return false;
        }

        return LocalBusiness::class === $to && null !== ($context['input']['class'] ?? null);
    }
}
