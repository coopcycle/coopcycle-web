<?php

namespace AppBundle\Domain\Task\Reactor;

use AppBundle\Domain\Task\Event\TaskIncidentReported;
use AppBundle\LoopEat\Client as LoopeatClient;
use Psr\Log\LoggerInterface;

class NotifyLoopeat
{
    public function __construct(
        private LoopEatClient $loopeatClient,
        private LoggerInterface $logger)
    {
    }

    public function __invoke(TaskIncidentReported $event)
    {
        $task = $event->getTask();

        if (!$task->isDropoff()) {
            return;
        }

        $reason = $event->getReason();

        if ($reason !== 'zero_waste_unexpected_returns') {
            return;
        }

        $incident = $event->getIncident();

        if (null === $incident) {
            return;
        }

        $metadata = $incident->getMetadata();

        $this->logger->info(
            sprintf('An incident for unexpected returns was reported with metadata "%s"', json_encode($metadata))
        );

        if (array_key_exists('loopeat_returns', $metadata)) {
            foreach ($metadata['loopeat_returns'] as $return) {
                // TODO Check if there is a change in what has been sent to avoid useless API calls
                $this->loopeatClient->updatePickupFormat($task->getDelivery()->getOrder(), $return['format_id'], $return['quantity']);
            }
        }
    }
}
