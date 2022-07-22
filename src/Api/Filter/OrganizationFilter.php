<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Organization;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class OrganizationFilter extends AbstractContextAwareFilter
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
