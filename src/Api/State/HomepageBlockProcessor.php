<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\UI\HomepageBlock;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

class HomepageBlockProcessor implements ProcessorInterface
{
    public function __construct(
        private ItemProvider $provider,
        private ProcessorInterface $persistProcessor,
        private EntityManagerInterface $entityManager)
    {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $blocks = $this->entityManager->getRepository(HomepageBlock::class)->findAll();
        foreach ($blocks as $block) {
            $this->entityManager->remove($block);
        }

        foreach ($data->blocks as $block) {
            $this->entityManager->persist($block);
        }

        $this->entityManager->flush();

        return $data->blocks;

        /** @var Homepage */
        // $homepage = $this->provider->provide($operation, $uriVariables, $context);

        // $homepage->setBlocks($data->getBlocks());

        // return $this->persistProcessor->process($homepage, $operation, $uriVariables, $context);
    }
}


