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

final class AssignedFilter extends AbstractFilter
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
        // otherwise filter is applied to order and page as well
        if (!$this->isPropertyEnabled($property, $resourceClass)) {
            return;
        }

        $user = $this->security->getUser();

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
