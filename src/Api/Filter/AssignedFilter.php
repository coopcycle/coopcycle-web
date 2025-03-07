<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class AssignedFilter extends AbstractContextAwareFilter
{
    private $tokenStorage;

    public function __construct(
        ManagerRegistry $managerRegistry,
        TokenStorageInterface $tokenStorage,
        $requestStack = null,
        LoggerInterface $logger = null,
        array $properties = null)
    {
        parent::__construct($managerRegistry, $requestStack, $logger, $properties);

        $this->tokenStorage = $tokenStorage;
    }

    protected function getUser()
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $user = $this->getUser();

        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_DISPATCHER')) {
            $isAssigned = filter_var($value, FILTER_VALIDATE_BOOLEAN);

            if (false === $isAssigned) {
                $queryBuilder
                    ->andWhere(sprintf('o.%s IS NULL', 'assignedTo'));
            } else {
                $queryBuilder
                    ->andWhere(sprintf('o.%s IS NOT NULL', 'assignedTo'));
            }
        }
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description[$property] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }
}
