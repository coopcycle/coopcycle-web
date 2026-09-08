<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\SearchQueryInput;
use AppBundle\Entity\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

final class SearchQueryCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security)
    {}

    /**
     * @param SearchQueryInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): SearchQuery
    {
        $searchQuery = new SearchQuery();
        $searchQuery->setUser($this->security->getUser());
        $searchQuery->setScope($data->scope);
        $searchQuery->setQuery($data->query);
        $searchQuery->setName($data->name);

        $this->entityManager->persist($searchQuery);
        $this->entityManager->flush();

        return $searchQuery;
    }
}
