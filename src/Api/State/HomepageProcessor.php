<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\UI\Homepage;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

class HomepageProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager)
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        /** @var Homepage */
        $homepage = $this->provider->provide($operation, $uriVariables, $context);

        $homepage->setBlocks($data->getBlocks());

        return $this->persistProcessor->process($homepage, $operation, $uriVariables, $context);
    }
}


