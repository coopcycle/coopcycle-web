<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\ShiftWeekClearInput;
use AppBundle\Api\State\ShiftWeekClearProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Deletes every shift of a given week in one go, so a dispatcher can start a
 * week's planning over without deleting shifts one by one. Refused on a
 * published week — couriers may already be relying on it.
 */
#[ApiResource(
    shortName: 'ShiftWeekClear',
    operations: [
        new Post(
            uriTemplate: '/shifts/clear_week',
            input: ShiftWeekClearInput::class,
            processor: ShiftWeekClearProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 200,
            normalizationContext: ['groups' => ['shift_week_clear']],
            denormalizationContext: ['groups' => ['shift_week_clear_create']]
        ),
    ]
)]
final class ShiftWeekClear
{
    #[Groups(['shift_week_clear'])]
    public int $cleared;

    public function __construct(int $cleared)
    {
        $this->cleared = $cleared;
    }
}
