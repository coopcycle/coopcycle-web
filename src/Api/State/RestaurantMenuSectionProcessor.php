<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\MenuInput;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\ProductTaxon;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Sylius\TaxonRepository;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;
// use Sylius\Component\Taxonomy\Model\Taxon;

class RestaurantMenuSectionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RestaurantMenuSectionProvider $sectionProvider,
        private readonly ProcessorInterface $persistProcessor,
        private readonly FactoryInterface $taxonFactory,
        private readonly TaxonRepository $taxonRepository,
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param MenuInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $menu = $this->taxonRepository->find($uriVariables['menuId']);

        if ($operation instanceof Put) {

            $section = $this->sectionProvider->provide($operation, $uriVariables, $context);

            foreach ($data->products as $product) {
                $section->addProduct($product);
            }

            $this->entityManager->flush();

            return $menu;
        }

        $section = $this->taxonFactory->createNew();

        $uuid = Uuid::uuid1()->toString();

        $section->setCode($uuid);
        $section->setSlug($uuid);
        $section->setName($data->name);

        $menu->addChild($section);

        $this->entityManager->flush();

        return $menu;
    }
}


