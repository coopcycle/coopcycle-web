<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\ShiftBatchInput;
use AppBundle\Api\State\ShiftBatchProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Creates many empty shifts at once, used to commit an accepted (and possibly
 * edited) demand-generated schedule.
 */
#[ApiResource(
    shortName: 'ShiftBatch',
    operations: [
        new Post(
            uriTemplate: '/shifts/batch',
            input: ShiftBatchInput::class,
            processor: ShiftBatchProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 201,
            normalizationContext: ['groups' => ['shift_batch']],
            denormalizationContext: ['groups' => ['shift_batch_create']]
        ),
    ]
)]
final class ShiftBatch
{
    #[Groups(['shift_batch'])]
    public int $created;

    public function __construct(int $created)
    {
        $this->created = $created;
    }
}
