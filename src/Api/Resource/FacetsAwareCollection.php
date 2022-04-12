<?php

namespace AppBundle\Api\Resource;

use AppBundle\Entity\LocalBusiness;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;

class FacetsAwareCollection extends ArrayCollection
{
    /**
     * @Groups({"restaurant_list"})
     */
    public $foo = 'bar';

    public function getFacets()
    {
        $cuisineFacet = [];
        $categoryFacet = [
            'featured' => 0,
            'exclusive' => 0,
        ];
        $typeFacet = [];

        foreach ($this as $element) {

            foreach ($element->getServesCuisine() as $c) {
                $name = $c->getName();
                if (isset($cuisineFacet[$name])) {
                    $cuisineFacet[$name] += 1;
                } else {
                    $cuisineFacet[$name] = 1;
                }
            }

            if ($element->isFeatured()) {
                $categoryFacet['featured'] += 1;
            }

            if ($element->isExclusive()) {
                $categoryFacet['exclusive'] += 1;
            }

            $keyForType = LocalBusiness::getKeyForType($element->getType());

            if (isset($typeFacet[$keyForType])) {
                $typeFacet[$keyForType] += 1;
            } else {
                $typeFacet[$keyForType] = 1;
            }
        }

        return [
            'category' => $categoryFacet,
            'cuisine'  => $cuisineFacet,
            'type'     => $typeFacet,
        ];
    }
}
