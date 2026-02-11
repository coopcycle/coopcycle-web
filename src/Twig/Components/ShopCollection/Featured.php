<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Featured extends ShopCollection
{
    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'category' => 'featured',
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans('homepage.featured');
    }

    public function getShops(): array
    {
        $exclusives = $this->repository->findFeatured();
        $exclusivesIterator = new SortableRestaurantIterator($exclusives, $this->timingRegistry);

        return iterator_to_array($exclusivesIterator);
    }
}
