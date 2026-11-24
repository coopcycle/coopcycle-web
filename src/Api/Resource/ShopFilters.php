<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShopFiltersProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The filters to offer when browsing the shops, in the order they should be
 * shown, with their labels already translated for the request's locale.
 *
 * Each entry is the pair of query parameters that narrows
 * GET /api/restaurants: `{"filter": "cuisine", "value": "italian"}` means
 * `?cuisine[]=italian`. Clients keep track of which one is selected
 * themselves.
 */
#[ApiResource(
    shortName: 'ShopFilters',
    operations: [
        new Get(
            uriTemplate: '/shop_filters',
            provider: ShopFiltersProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['shop_filters']]
)]
final class ShopFilters
{
    /**
     * @var array<int, array{id: string, filter: string, value: string, label: string}>
     */
    #[Groups(['shop_filters'])]
    public array $filters;

    /**
     * @param array<int, array{id: string, filter: string, value: string, label: string}> $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }
}
