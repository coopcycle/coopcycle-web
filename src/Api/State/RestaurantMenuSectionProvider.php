<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\Sylius\TaxonRepository;
use Doctrine\ORM\EntityManagerInterface;
use ShipMonk\DoctrineEntityPreloader\EntityPreloader;

final class RestaurantMenuSectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly TaxonRepository $taxonRepository,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $menu = $this->taxonRepository->find($uriVariables['menuId']);
        $section = $this->taxonRepository->find($uriVariables['sectionId']);

        return $section;
    }
}
