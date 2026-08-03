<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\BankHolidaysProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Public/bank holidays for the coop's country, over a date range
 * (?date[after]=YYYY-MM-DD&date[before]=YYYY-MM-DD). Informational only —
 * used to visually highlight holidays in the shift planning grid.
 */
#[ApiResource(
    shortName: 'BankHolidays',
    operations: [
        new Get(
            uriTemplate: '/bank_holidays',
            provider: BankHolidaysProvider::class,
            security: 'is_granted(\'ROLE_COURIER\')'
        ),
    ],
    normalizationContext: ['groups' => ['bank_holidays']]
)]
final class BankHolidays
{
    /**
     * @var array<int, array{date: string, name: string}>
     */
    #[Groups(['bank_holidays'])]
    public array $holidays;

    /**
     * @param array<int, array{date: string, name: string}> $holidays
     */
    public function __construct(array $holidays = [])
    {
        $this->holidays = $holidays;
    }
}
