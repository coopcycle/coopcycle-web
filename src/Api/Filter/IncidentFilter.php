<?php

namespace AppBundle\Api\Filter;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

final class IncidentFilter extends AbstractFilter
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly IriConverterInterface $iriConverter,
        ?LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    private array $storeIri = [];
    private array $restaurantIri = [];
    private array $customerIri = [];

    private const PRIORITY_MAP = [
        'HIGH' => 1,
        'MEDIUM' => 2,
        'LOW' => 3,
    ];

    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (!$this->isPropertyEnabled($property, $resourceClass) || empty($value)) {
            return;
        }

        $values = is_array($value) ? $value : [$value];

        match ($property) {
            'status' => $this->addStringFilter($queryBuilder, $property, $queryNameGenerator->generateParameterName($property), $value),
            'priority' => $this->addPriorityFilter($queryBuilder, $queryNameGenerator, $value),
            'store' => $this->storeIri = array_merge($this->storeIri, $values),
            'restaurant' => $this->restaurantIri = array_merge($this->restaurantIri, $values),
            'customer' => $this->customerIri = array_merge($this->customerIri, $values),
            'createdBy' => $this->addEntityFilter($queryBuilder, 'createdBy', $queryNameGenerator->generateParameterName('createdBy'), $values),
            default => null,
        };
    }

    private function addPriorityFilter(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, mixed $value): void
    {
        $param = $queryNameGenerator->generateParameterName('priority');
        $priorityValue = self::PRIORITY_MAP[strtoupper($value)] ?? (int) $value;
        $queryBuilder->andWhere(sprintf('o.priority = :%s', $param));
        $queryBuilder->setParameter($param, $priorityValue);
    }

    public function apply(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operation, $context);

        $hasStore = !empty($this->storeIri);
        $hasRestaurant = !empty($this->restaurantIri);
        $hasCustomer = !empty($this->customerIri);

        if (!$hasStore && !$hasRestaurant && !$hasCustomer) {
            return;
        }

        $storeEntities = $this->irisToEntities($this->storeIri);
        $restaurantEntities = $this->irisToEntities($this->restaurantIri);
        $customerEntities = $this->irisToEntities($this->customerIri);

        $queryBuilder->join('o.task', 't');
        $queryBuilder->join('t.delivery', 'd');

        if ($hasCustomer) {
            $queryBuilder->join('d.order', 'o_order');
            $param = $queryNameGenerator->generateParameterName('customer');
            $queryBuilder->andWhere(sprintf('o_order.customer IN (:%s)', $param));
            $queryBuilder->setParameter($param, $customerEntities);
        }

        if ($hasStore && $hasRestaurant) {
            if (!$hasCustomer) {
                $queryBuilder->leftJoin('d.order', 'o_order');
            }
            $queryBuilder->leftJoin('o_order.vendors', 'vendor');
            $storeParam = $queryNameGenerator->generateParameterName('store');
            $restaurantParam = $queryNameGenerator->generateParameterName('restaurant');
            $queryBuilder->andWhere(sprintf('(d.store IN (:%s) OR vendor.restaurant IN (:%s))', $storeParam, $restaurantParam));
            $queryBuilder->setParameter($storeParam, $storeEntities);
            $queryBuilder->setParameter($restaurantParam, $restaurantEntities);
        } elseif ($hasStore) {
            $param = $queryNameGenerator->generateParameterName('store');
            $queryBuilder->andWhere(sprintf('d.store IN (:%s)', $param));
            $queryBuilder->setParameter($param, $storeEntities);
        } elseif ($hasRestaurant) {
            if (!$hasCustomer) {
                $queryBuilder->join('d.order', 'o_order');
            }
            $queryBuilder->join('o_order.vendors', 'vendor');
            $param = $queryNameGenerator->generateParameterName('restaurant');
            $queryBuilder->andWhere(sprintf('vendor.restaurant IN (:%s)', $param));
            $queryBuilder->setParameter($param, $restaurantEntities);
        }

        $this->storeIri = [];
        $this->restaurantIri = [];
        $this->customerIri = [];
    }

    private function addStringFilter(QueryBuilder $queryBuilder, string $property, string $parameterName, mixed $value): void
    {
        $queryBuilder->andWhere(sprintf('o.%s = :%s', $property, $parameterName));
        $queryBuilder->setParameter($parameterName, $value);
    }

    private function addEntityFilter(QueryBuilder $queryBuilder, string $property, string $parameterName, array $iris): void
    {
        $entities = $this->irisToEntities($iris);
        $queryBuilder->andWhere(sprintf('o.%s IN (:%s)', $property, $parameterName));
        $queryBuilder->setParameter($parameterName, $entities);
    }

    private function irisToEntities(array $iris): array
    {
        return array_map(fn($iri) => $this->iriConverter->getResourceFromIri($iri), $iris);
    }

    public function getDescription(string $resourceClass): array
    {
        $description = [
            'status' => [
                'property' => 'status',
                'type' => 'string',
                'required' => false,
            ],
            'priority' => [
                'property' => 'priority',
                'type' => 'int',
                'required' => false,
            ],
            'store' => [
                'property' => 'store',
                'type' => 'string',
                'required' => false,
            ],
            'restaurant' => [
                'property' => 'restaurant',
                'type' => 'string',
                'required' => false,
            ],
            'customer' => [
                'property' => 'customer',
                'type' => 'string',
                'required' => false,
            ],
            'createdBy' => [
                'property' => 'createdBy',
                'type' => 'string',
                'required' => false,
            ],
        ];

        return $description;
    }
}
