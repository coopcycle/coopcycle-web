<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Type extends ShopCollection
{
    public string $type;

    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'type' => $this->type,
        ]);
    }

    public function getTitle(): string
    {
        $shopType = LocalBusiness::getTypeForKey($this->type);

        return $this->translator->trans(LocalBusiness::getTransKeyForType($shopType));
    }

    protected function doGetShops(): array
    {
        $shopType = LocalBusiness::getTypeForKey($this->type);

        $typeRepository = $this->repository->withTypeFilter($shopType);

        $items = $typeRepository->findAllForType();

        $iterator = new SortableRestaurantIterator($items, $this->timingRegistry);

        return iterator_to_array($iterator);
    }

    protected function getCacheKeyParts(): array
    {
        return [
            $this->type
        ];
    }
}
