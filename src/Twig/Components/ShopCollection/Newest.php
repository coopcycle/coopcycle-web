<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Newest extends ShopCollection
{
    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'category' => 'new',
        ]);
    }

    public function getTitle(): string
    {
        return $this->translator->trans('homepage.shops.new');
    }

    public function getShops(): array
    {
        $news = $this->repository->findLatest();
        $newsIterator = new SortableRestaurantIterator($news, $this->timingRegistry);

        return iterator_to_array($newsIterator);
    }
}
