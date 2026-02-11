<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Exclusive extends ShopCollection
{
    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'category' => 'exclusive',
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans('homepage.exclusive');
    }

    public function getShops(): array
    {
        $exclusives = $this->repository->findExclusives();
        $exclusivesIterator = new SortableRestaurantIterator($exclusives, $this->timingRegistry);

        return iterator_to_array($exclusivesIterator);
    }
}
