<?php

namespace AppBundle\Serializer\JsonLd;

use ApiPlatform\JsonLd\Serializer\ItemNormalizer;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Sylius\Product\ProductOptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class RestaurantMenuNormalizer implements NormalizerInterface, DenormalizerInterface
{
    public function __construct(
        private ItemNormalizer $normalizer,
        private UrlGeneratorInterface $urlGenerator,
        private ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory)
    {
    }

    public function normalize($object, $format = null, array $context = array())
    {
        // Force the IRI of the root resource to correspond to the GET operation
        // If we don't do this, the IRI when doing PUT /api/restaurants/menus/1/sections/1 can be wrong
        $context['operation'] = $this->resourceMetadataFactory->create(Taxon::class)->getOperation();

        $data = $this->normalizer->normalize($object, $format, $context);

        if (!$object->isRoot()) {
            // FIXME
            // Ugly but works and is tested in Behat
            // This should be handled by a custom normalizer for Taxon class
            $data['@id'] = $this->urlGenerator->generate('_api_/restaurants/menus/{id}/sections/{sectionId}_get', [
                'id' => $object->getParent()->getId(),
                'sectionId' => $object->getId(),
            ]);
        } else {
            unset($data['description']);
        }

        if (isset($data['hasMenuSection'])) {
            foreach ($data['hasMenuSection'] as $i => $menuSection) {
                $data['hasMenuSection'][$i]['@type'] = 'MenuSection';
            }
        }

        if (isset($data['hasMenuItem'])) {
            foreach ($data['hasMenuItem'] as $i => $menuItem) {
                $data['hasMenuItem'][$i]['@type'] = 'MenuItem';
            }
        }

        return $data;
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->supportsNormalization($data, $format) && $data instanceof Taxon;
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return [];
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return false;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Taxon::class => true, // supports*() call result is cached
        ];
    }
}
