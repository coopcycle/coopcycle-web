<?php

namespace AppBundle\Api\State;

use AppBundle\Entity\UI\HomepageBlock;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

class HomepageBlockProcessor implements ProcessorInterface
{
    public function __construct(
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
    }
}


