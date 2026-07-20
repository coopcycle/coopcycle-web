<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
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

    protected function doGetShops(): array
    {
        $exclusives = $this->repository->findFeatured();

        return $this->sortShops($exclusives);
    }
}
