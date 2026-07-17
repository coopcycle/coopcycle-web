<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
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

        return $this->sortShops($shopsByCuisine);
    }

    protected function getCacheKeyParts(): array
    {
        return [
            $this->cuisine
        ];
    }
}
