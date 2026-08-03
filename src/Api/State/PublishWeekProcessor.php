<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\PublishWeekInput;
use AppBundle\Service\SchedulePublisher;

class PublishWeekProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly SchedulePublisher $schedulePublisher)
    {}

    /**
     * @param PublishWeekInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $weekStart = (new \DateTimeImmutable($data->week))->modify('monday this week');

        return $this->schedulePublisher->publish($weekStart);
    }
}
