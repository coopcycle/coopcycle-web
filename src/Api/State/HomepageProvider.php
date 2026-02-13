<?php

namespace AppBundle\Api\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use AppBundle\Entity\UI\Homepage;
use Doctrine\ORM\EntityManagerInterface;

final class HomepageProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $provider,
        private readonly EntityManagerInterface $entityManager)
    {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->entityManager->getRepository(Homepage::class)->findOneBy([]);
    }
}
