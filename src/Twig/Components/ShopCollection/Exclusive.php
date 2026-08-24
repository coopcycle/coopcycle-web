<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
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

    protected function doGetShops(): array
    {
        $exclusives = $this->repository->findExclusives();

        return $this->sortShops($exclusives);
    }
}
