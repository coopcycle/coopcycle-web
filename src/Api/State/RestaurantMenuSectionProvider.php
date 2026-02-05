<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Sylius\TaxonRepository;

final class RestaurantMenuSectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly TaxonRepository $taxonRepository)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $menu = $this->taxonRepository->find($uriVariables['id']);
        $section = $this->taxonRepository->find($uriVariables['sectionId']);

        return $section;
    }
}
