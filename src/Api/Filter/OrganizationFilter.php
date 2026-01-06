<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use AppBundle\Entity\Organization;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Symfony\Component\Security\Core\Security;

final class OrganizationFilter implements FilterInterface
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

        // Only admin can filter by organization
        if ($user->hasRole('ROLE_ADMIN')) {
            $orgName = filter_var($value);
            $alias = $queryBuilder->getRootAliases()[0];
            $valueParameter = $queryNameGenerator->generateParameterName('org_name');

            $queryBuilder->andWhere(sprintf('org.name = :%s', $valueParameter));
            $queryBuilder->setParameter($valueParameter, $orgName);
            $queryBuilder->innerJoin(Organization::class, 'org', Expr\Join::WITH, sprintf('%s.organization = org.id', $alias));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        // For BC, this function is not useful anymore when documentation occurs on the Parameter
        return [];
    }
}
