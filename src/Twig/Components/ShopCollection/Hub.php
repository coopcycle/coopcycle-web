<?php

namespace AppBundle\Twig\Components\ShopCollection;

use AppBundle\Entity\Hub as HubEntity;
use AppBundle\Twig\Components\ShopCollection;
use AppBundle\Utils\SortableRestaurantIterator;
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

    public function getShops(): array
    {
        $iterator = new SortableRestaurantIterator($this->hub->getRestaurants(), $this->timingRegistry);

        return array_slice(iterator_to_array($iterator), 0, 15);
    }
}
