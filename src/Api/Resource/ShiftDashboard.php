<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use AppBundle\Api\State\ShiftDashboardProvider;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Per-week slot fill rate (assignments/slots) for the shift planning grid,
 * over the next N weeks (?weeks=, default 5). Used by the "dashboard" view
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
     *     status: string
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
     *     status: string
     * }> $weeks
     */
    public function __construct(array $weeks = [])
    {
        $this->weeks = $weeks;
    }
}
