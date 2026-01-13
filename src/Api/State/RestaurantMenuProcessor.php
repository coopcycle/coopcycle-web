<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\MenuInput;
use AppBundle\Entity\LocalBusiness;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Sylius\Component\Resource\Factory\FactoryInterface;

class RestaurantMenuProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly ProcessorInterface $persistProcessor,
        private readonly FactoryInterface $taxonFactory,
        private readonly EntityManagerInterface $entityManager)
    {}

    /**
     * @param MenuInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
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


