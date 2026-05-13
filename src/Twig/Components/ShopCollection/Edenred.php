<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Edenred extends ShopCollection
{
    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'category' => 'edenred',
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans('homepage.edenred');
    }

    protected function doGetShops(): array
    {
        $shops = $this->repository->findEdenredEnabled();
        $iterator = new SortableRestaurantIterator($shops, $this->timingRegistry);

        return iterator_to_array($iterator);
    }
}
