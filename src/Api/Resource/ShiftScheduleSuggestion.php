<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\GenerateScheduleInput;
use AppBundle\Api\State\GenerateScheduleProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A demand-driven schedule proposal for a week. This is NOT persisted: the
 * dispatcher reviews (and can tweak) the proposed shifts against the demand vs
 * coverage overlay before committing them via /shifts/batch.
 */
#[ApiResource(
    shortName: 'ShiftScheduleSuggestion',
    operations: [
        new Post(
            uriTemplate: '/shifts/generate_schedule',
            input: GenerateScheduleInput::class,
            processor: GenerateScheduleProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 200,
            normalizationContext: ['groups' => ['shift_schedule']],
            denormalizationContext: ['groups' => ['shift_schedule_create']]
        ),
    ]
)]
final class ShiftScheduleSuggestion
{
    /**
     * Proposed shifts: [{ type, startsAt, endsAt, slots }]
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['shift_schedule'])]
    public array $shifts;

    /**
     * Per-day demand vs coverage, for the overlay chart:
     * [{ date, dow, buckets: [{ hour, demand, coverage }] }]
     * @var array<int, array<string, mixed>>
     */
    #[Groups(['shift_schedule'])]
    public array $days;

    /**
     * @var array<string, mixed>
     */
    #[Groups(['shift_schedule'])]
    public array $meta;

    public function __construct(array $shifts, array $days, array $meta)
    {
        $this->shifts = $shifts;
        $this->days = $days;
        $this->meta = $meta;
    }
}
