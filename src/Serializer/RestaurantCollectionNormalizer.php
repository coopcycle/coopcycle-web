<?php

namespace AppBundle\Serializer;

use AppBundle\Entity\LocalBusiness;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use AppBundle\Api\Resource\FacetsAwareCollection;

final class RestaurantCollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    private $decorated;

    public function __construct(NormalizerInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizer(NormalizerInterface $normalizer)
    {
        if ($this->decorated instanceof NormalizerAwareInterface) {
            $this->decorated->setNormalizer($normalizer);
        }
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->decorated->supportsNormalization($data, $format);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        $data = $this->decorated->normalize($object, $format, $context);

        if (!is_object($object) || !$object instanceof FacetsAwareCollection) {
            return $data;
        }

        $rawFacets = $object->getFacets();

        $categoryFacet = [
            'parameter' => 'category',
            'label' => 'Category', // TODO Translate
            'values' => []
        ];
        $cuisineFacet = [
            'parameter' => 'cuisine',
            'label' => 'Cuisine', // TODO Translate
            'values' => []
        ];
        $typeFacet = [
            'parameter' => 'type',
            'label' => 'Type', // TODO Translate
            'values' => []
        ];

        foreach ($rawFacets['category'] as $category => $categoryCount) {
            $categoryFacet['values'][] = [
                'value' => $category,
                'label' => $category, // TODO Translate
                'count' => $categoryCount,
            ];
        }
        foreach ($rawFacets['cuisine'] as $cuisine => $cuisineCount) {
            $cuisineFacet['values'][] = [
                'value' => $cuisine,
                'label' => $cuisine, // TODO Translate
                'count' => $cuisineCount,
            ];
        }
        foreach ($rawFacets['type'] as $type => $typeCount) {
            $typeFacet['values'][] = [
                'value' => $type,
                'label' => $type, // TODO Translate
                'count' => $typeCount,
            ];
        }

        $data['facets'] = [
            $categoryFacet,
            $cuisineFacet,
            $typeFacet,
        ];

        return $data;
    }
}
