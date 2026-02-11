<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Cuisine extends ShopCollection
{
    public string $cuisine;

    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'cuisine' => [$this->cuisine],
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans($this->cuisine, [], 'cuisines');
    }

    protected function doGetShops(): array
    {
        $shopsByCuisine = $this->repository->findByCuisine($this->cuisine);
        $shopsByCuisineIterator = new SortableRestaurantIterator($shopsByCuisine, $this->timingRegistry);

        return iterator_to_array($shopsByCuisineIterator);
    }

    protected function getCacheKeyParts(): array
    {
        return [
            $this->cuisine
        ];
    }
}
