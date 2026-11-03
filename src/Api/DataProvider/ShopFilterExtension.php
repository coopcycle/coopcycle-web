<?php

namespace AppBundle\Api\DataProvider;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\LocalBusinessRepository;
use Doctrine\ORM\QueryBuilder;

/**
 * Lets GET /api/restaurants be narrowed the same way /shops is:
 * ?category=, ?cuisine[]=, ?type= and ?collection=.
 *
 * The clauses themselves come from LocalBusinessRepository, so that the
 * website and the apps select the same shops for a given filter.
 */
class ShopFilterExtension implements QueryCollectionExtensionInterface
{
    private const SUPPORTED = ['category', 'cuisine', 'type', 'collection'];

    public function __construct(private LocalBusinessRepository $shopRepository)
    {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []): void
    {
        if (LocalBusiness::class !== $resourceClass) {
            return;
        }

        if (!$operation instanceof GetCollection) {
            return;
        }

        $filters = $this->extractFilters($context['filters'] ?? []);

        if (count($filters) === 0) {
            return;
        }

        $this->shopRepository->applyFilters(
            $queryBuilder,
            $filters,
            $queryBuilder->getRootAliases()[0]
        );
    }

    /**
     * @return array<string, string|string[]>
     */
    private function extractFilters(array $queryFilters): array
    {
        $filters = [];

        foreach (self::SUPPORTED as $name) {

            $value = $queryFilters[$name] ?? null;

            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            if ('cuisine' === $name) {
                // ?cuisine[]=italian&cuisine[]=japanese, or a single ?cuisine=
                $filters[$name] = array_values(array_filter((array) $value, 'is_string'));
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            // A type is addressed by its slug ("fast-food-restaurant"), as it
            // is on the website, not by its schema.org URL.
            if ('type' === $name) {
                $type = LocalBusiness::getTypeForKey($value);
                if (null === $type) {
                    continue;
                }
                $value = $type;
            }

            $filters[$name] = $value;
        }

        return $filters;
    }
}
