<?php

namespace AppBundle\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use AppBundle\Api\Dto\GenerateScheduleInput;
use AppBundle\Api\Resource\ShiftScheduleSuggestion;
use AppBundle\Service\Shift\ScheduleGenerator;

final class GenerateScheduleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ScheduleGenerator $generator)
    {}

    /**
     * @param GenerateScheduleInput $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): ShiftScheduleSuggestion
    {
        $monday = (new \DateTimeImmutable($data->week))->modify('monday this week');

        $result = $this->generator->generate($monday);

        return new ShiftScheduleSuggestion(
            $result['shifts'],
            $result['days'],
            $result['meta']
        );
    }
}
