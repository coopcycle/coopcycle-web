<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\Organization;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class OrganizationFilter extends AbstractFilter
{
    private $security;

    public function __construct(
        ManagerRegistry $managerRegistry,
        Security $security,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null)
    {
        $this->security = $security;

        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = [])
    {
        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $user = $this->security->getUser();

        if ($user->hasRole('ROLE_ADMIN')) {
            $orgName = filter_var($value);

            $valueParameter = $queryNameGenerator->generateParameterName('org_name');

            $queryBuilder->andWhere(sprintf('org.name = :%s', $valueParameter));
            $queryBuilder->setParameter($valueParameter, $orgName);
            $queryBuilder->innerJoin(Organization::class, 'org', Expr\Join::WITH, 'o.organization = org.id');
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
