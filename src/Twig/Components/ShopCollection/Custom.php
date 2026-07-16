<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Entity\LocalBusiness\Collection;
use AppBundle\Twig\Components\ShopCollection;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Custom extends ShopCollection
{
    public string $slug;

    public function getUrl(): string
    {
        return $this->urlGenerator->generate('shops', [
            'collection' => $this->slug,
        ]);
    }

    public function getTitle(): string
    {
        $collection = $this->entityManager->getRepository(Collection::class)->findOneBy(['slug' => $this->slug]);

        return $collection->getTitle();
    }

    protected function doGetShops(): array
    {
        $shops = $this->repository->findByCollection($this->slug);

        return $this->sortShops($shops);
    }

    protected function getCacheKeyParts(): array
    {
        return [
            $this->slug
        ];
    }
}
