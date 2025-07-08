<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\PropertyInfo\Type;

final class OrderStoreFilter extends AbstractFilter
{
    private string $storeIdProperty = 'delivery.store.id';
    private string $storeIdAlias = 'store';

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        // expose alias in the API instead of a path to a nested property
        if ($this->storeIdAlias === $property) {

            $alias = $queryBuilder->getRootAliases()[0];

            [$alias, $field] = $this->addJoinsForNestedProperty($this->storeIdProperty, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);

            $valueParameter = $queryNameGenerator->generateParameterName($field);

            $queryBuilder
                ->andWhere(\sprintf('%s.%s = :%s', $alias, $field, $valueParameter))
                ->setParameter($valueParameter, $value, (string) $this->getDoctrineFieldType($property, $resourceClass));
        }
    }

    public function getDescription(string $resourceClass): array
    {
        return [
            'store' => [
                'property' => $this->storeIdAlias,
                'type' => Type::BUILTIN_TYPE_INT,
                'required' => false,
                'is_collection' => false,
            ],
            'store[]'=> [
                'property' => $this->storeIdAlias,
                'type' => Type::BUILTIN_TYPE_INT,
                'required' => false,
                'is_collection' => true,
            ]
        ];
    }
}
