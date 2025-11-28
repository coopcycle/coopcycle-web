<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Security;

final class AssignedFilter implements FilterInterface
{
    public function __construct(
        private readonly Security $security
    ) {
    }

    public function apply(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parameter = $context['parameter'] ?? null;
        $value = $parameter?->getValue();

        // The parameter may not be present
        if ($value instanceof ParameterNotFound || null === $value) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            return;
        }

        // Only admin/dispatcher can filter by assignment status
        if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_DISPATCHER')) {
            $isAssigned = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            $alias = $queryBuilder->getRootAliases()[0];

            if (false === $isAssigned) {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s IS NULL', $alias, 'assignedTo'));
            } else {
                $queryBuilder
                    ->andWhere(sprintf('%s.%s IS NOT NULL', $alias, 'assignedTo'));
            }
        }
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
