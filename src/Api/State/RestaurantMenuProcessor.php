<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\MenuInput;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\TaxonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;

class RestaurantMenuProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly RestaurantMenuSectionProvider $sectionProvider,
        private readonly ProcessorInterface $persistProcessor,
        private readonly FactoryInterface $taxonFactory,
        private readonly TaxonRepository $taxonRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly IriConverterInterface $iriConverter,
        private readonly LoggerInterface $logger)
    {}

    /**
     * @param MenuInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if ($operation instanceof Put) {

            $menu = $this->taxonRepository->find($uriVariables['menuId']);

            $nestedTreeRepository = $this->taxonRepository->getNestedTreeRepository();

            $originalPositions = new \SplObjectStorage();
            foreach ($menu->getChildren() as $position => $child) {
                $originalPositions[$child] = $position;
            }

            foreach ($data->sections as $newPosition => $iri) {

                $section = $this->iriConverter->getResourceFromIri($iri);
                $originalPosition = $originalPositions[$section];

                // The position is not updated by moveUp/moveDown
                $section->setPosition($newPosition);

                $offset = $newPosition - $originalPosition;

                if ($offset > 0) {
                    $nestedTreeRepository->moveDown($section, $offset);
                } elseif ($offset < 0) {
                    $nestedTreeRepository->moveUp($section, abs($offset));
                }

                $this->logger->debug(sprintf('Menu section "%s" was moved from %d to %d', $section->getName(), $originalPosition, $newPosition));
            }

            // The moveUp/moveDown functions do flush changes,
            // but we still need to flush the position changes
            $this->entityManager->flush();

            // We make sure to clear the entity manager
            // so that object is fully reloaded from database
            $this->entityManager->clear();

            return $this->taxonRepository->find($uriVariables['menuId']);
        }

        /** @var LocalBusiness */
        $restaurant = $this->provider->provide($operation, $uriVariables, $context);

        $menuTaxon = $this->taxonFactory->createNew();

        $uuid = Uuid::uuid1()->toString();

        $menuTaxon->setCode($uuid);
        $menuTaxon->setSlug($uuid);
        $menuTaxon->setName($data->name);

        $restaurant->addTaxon($menuTaxon);

        $this->persistProcessor->process($restaurant, $operation, $uriVariables, $context);

        return $menuTaxon;
    }
}


