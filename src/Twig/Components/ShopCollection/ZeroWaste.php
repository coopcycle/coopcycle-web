<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class ZeroWaste extends ShopCollection
{
    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'category' => 'zerowaste',
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans('homepage.zerowaste');
    }

    protected function doGetShops(): array
    {
        $shops = $this->repository->findZeroWaste();
        $iterator = new SortableRestaurantIterator($shops, $this->timingRegistry);

        return iterator_to_array($iterator);
    }
}
