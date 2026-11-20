<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShopCuisinesProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * The cuisines a customer can narrow the shops by: the ones at least one shop
 * serves, sorted by label, each with its icon as SVG markup.
 *
 * The strip the /shops page shows above the list, in a form a mobile app can
 * render too. Entries have the same shape as /api/shop_filters, so
 * `{"filter": "cuisine", "value": "italian"}` means `?cuisine[]=italian`; a
 * client that already holds the shops can instead match the value against a
 * shop's own `cuisines`.
 */
#[ApiResource(
    shortName: 'ShopCuisines',
    operations: [
        new Get(
            uriTemplate: '/shop_cuisines',
            provider: ShopCuisinesProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['shop_cuisines']]
)]
final class ShopCuisines
{
    /**
     * @var array<int, array{id: string, filter: string, value: string, label: string, icon: string|null}>
     */
    #[Groups(['shop_cuisines'])]
    public array $cuisines;

    /**
     * @param array<int, array{id: string, filter: string, value: string, label: string, icon: string|null}> $cuisines
     */
    public function __construct(array $cuisines = [])
    {
        $this->cuisines = $cuisines;
    }
}
