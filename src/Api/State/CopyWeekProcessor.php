<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\CopyWeekInput;
use AppBundle\Service\ShiftManager;

class CopyWeekProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ShiftManager $shiftManager)
    {}

    /**
     * @param CopyWeekInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $sourceMonday = (new \DateTimeImmutable($data->sourceWeek))->modify('monday this week');
        $targetMonday = (new \DateTimeImmutable($data->targetWeek))->modify('monday this week');

        return $this->shiftManager->copyWeek($sourceMonday, $targetMonday);
    }
}
