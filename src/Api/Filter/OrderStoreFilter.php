<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Core\Api\IdentifiersExtractorInterface;
use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use AppBundle\Entity\Sylius\Order;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class OrderStoreFilter extends SearchFilter
{
    private string $storeIdProperty = 'delivery.store.id';
    private string $storeIdAlias = 'store';

    public function __construct(
        ManagerRegistry $managerRegistry,
        ?RequestStack $requestStack,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor = null,
        LoggerInterface $logger = null,
        IdentifiersExtractorInterface $identifiersExtractor = null,
        NameConverterInterface $nameConverter = null
    )
    {
        $properties = [
            $this->storeIdProperty => 'exact',
        ];

        parent::__construct($managerRegistry, $requestStack, $iriConverter, $propertyAccessor, $logger, $properties, $identifiersExtractor, $nameConverter);
    }

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null)
    {
        if ($resourceClass !== Order::class) {
            return;
        }

        // expose alias in the API instead of a path to a nested property
        if ($this->storeIdAlias === $property) {
            parent::filterProperty('delivery.store.id', $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName);
        }
    }

    private function renameProperty(array &$description, string $property, string $alias, string $suffix): void
    {
        $description[$alias.$suffix] = $description[$property.$suffix];
        $description[$alias.$suffix]['property'] = $alias;
        unset($description[$property.$suffix]);
    }

    public function getDescription(string $resourceClass): array
    {
        $result = parent::getDescription($resourceClass);

        $this->renameProperty($result, $this->storeIdProperty, $this->storeIdAlias, '');
        $this->renameProperty($result, $this->storeIdProperty, $this->storeIdAlias, '[]');

        return $result;
    }
}
