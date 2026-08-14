<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShiftDashboardProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Per-week slot fill rate (assignments/slots) for the shift planning grid,
 * over N weeks (?weeks=, default 5) starting at ?from= (any date, aligned to
 * its Monday; defaults to the current week). Used by the week overview strip
 * next to the weekly grid to show staffing completeness at a glance.
 */
#[ApiResource(
    shortName: 'ShiftDashboard',
    operations: [
        new Get(
            uriTemplate: '/shifts/dashboard',
            provider: ShiftDashboardProvider::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')'
        ),
    ],
    normalizationContext: ['groups' => ['shift_dashboard']]
)]
final class ShiftDashboard
{
    /**
     * @var array<int, array{
     *     weekStart: string,
     *     weekEnd: string,
     *     totalSlots: int,
     *     totalAssignments: int,
     *     fillRate: float,
     *     published: bool
     * }>
     */
    #[Groups(['shift_dashboard'])]
    public array $weeks;

    /**
     * @param array<int, array{
     *     weekStart: string,
     *     weekEnd: string,
     *     totalSlots: int,
     *     totalAssignments: int,
     *     fillRate: float,
     *     published: bool
     * }> $weeks
     */
    public function __construct(array $weeks = [])
    {
        $this->weeks = $weeks;
    }
}
