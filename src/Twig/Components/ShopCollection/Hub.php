<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Entity\Hub as HubEntity;
use AppBundle\Twig\Components\ShopCollection;
use Cocur\Slugify\SlugifyInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent(template: 'components/ShopCollection.html.twig')]
class Hub extends ShopCollection
{
    public HubEntity $hub;

    private SlugifyInterface $slugify;

    #[Required]
    public function setSlugify(SlugifyInterface $slugify): void
    {
        $this->slugify = $slugify;
    }

    public function getUrl(): string
    {
        return $this->urlGenerator->generate('hub', [
            'id' => $this->hub->getId(),
            'slug' => $this->slugify->slugify($this->hub->getName()),
        ]);
    }

    public function getTitle(): string
    {
        return $this->hub->getName();
    }

    protected function doGetShops(): array
    {
        $shops = $this->sortShops(iterator_to_array($this->hub->getRestaurants()));

        return array_slice($shops, 0, 15);
    }
}
