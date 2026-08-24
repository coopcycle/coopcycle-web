<?php

namespace AppBundle\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use AppBundle\Api\Dto\ShiftDispatchSyncInput;
use AppBundle\Api\State\ShiftDispatchSyncProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Manually adds every courier assigned to a shift in a given week to the
 * dispatch (empty TaskList per assigned day), triggered from the planning UI.
 */
#[ApiResource(
    shortName: 'ShiftDispatchSync',
    operations: [
        new Post(
            uriTemplate: '/shifts/dispatch_sync',
            input: ShiftDispatchSyncInput::class,
            processor: ShiftDispatchSyncProcessor::class,
            security: 'is_granted(\'ROLE_DISPATCHER\')',
            status: 200,
            normalizationContext: ['groups' => ['shift_dispatch_sync']],
            denormalizationContext: ['groups' => ['shift_dispatch_sync_create']]
        ),
    ]
)]
final class ShiftDispatchSync
{
    #[Groups(['shift_dispatch_sync'])]
    public int $added;

    public function __construct(int $added)
    {
        $this->added = $added;
    }
}
