<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Twig\Components\ShopCollection;
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

    protected function doGetShops(): array
    {
        $news = $this->repository->findLatest();

        return $this->sortShops($news);
    }
}
