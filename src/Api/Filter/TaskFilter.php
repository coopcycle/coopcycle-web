<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class TaskFilter extends AbstractFilter
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

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $user = $this->security->getUser();

        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        if (!($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_DISPATCHER')) && $user->hasRole('ROLE_COURIER')) {

            $parameterName = $queryNameGenerator->generateParameterName('user');
            $queryBuilder
                ->andWhere(sprintf('o.%s IS NOT NULL', 'assignedTo'))
                ->andWhere(sprintf('o.%s = :%s', 'assignedTo', $parameterName))
                ->setParameter($parameterName, $user);
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
