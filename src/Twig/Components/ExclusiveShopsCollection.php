<?php

namespace AppBundle\Twig\Components;

use AppBundle\Entity\LocalBusinessRepository;
use AppBundle\Service\TimingRegistry;
use AppBundle\Utils\SortableRestaurantIterator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class ExclusiveShopsCollection
{
    public function __construct(
        private LocalBusinessRepository $repository,
        private TimingRegistry $timingRegistry,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator)
    {
    }

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

    public function getShops(): array
    {
        $exclusives = $this->repository->findExclusives();
        $exclusivesIterator = new SortableRestaurantIterator($exclusives, $this->timingRegistry);

        return iterator_to_array($exclusivesIterator);
    }
}
