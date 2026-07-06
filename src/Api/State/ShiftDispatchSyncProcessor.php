<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\ShiftDispatchSyncInput;
use AppBundle\Api\Resource\ShiftDispatchSync;
use AppBundle\Service\ShiftManager;

final class ShiftDispatchSyncProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShiftManager $shiftManager)
    {}

    /**
     * @param ShiftDispatchSyncInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftDispatchSync
    {
        $monday = (new \DateTimeImmutable($data->week))->modify('monday this week');

        $added = $this->shiftManager->addWeekToDispatch($monday);

        return new ShiftDispatchSync(count($added));
    }
}
