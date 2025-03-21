<?php

namespace AppBundle\ExpressionLanguage;

use AppBundle\Entity\TimeSlot\TimeSlotAwareInterface;
use Psr\Log\LoggerInterface;

class TimeSlotResolver
{
    private static LoggerInterface $logger;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public function __construct(
        private TimeSlotAwareInterface $entity,
    )
    {
    }

    public function equal($name)
    {
        TimeSlotResolver::$logger->info('TimeSlotResolver::equal', [
            'entity' => $this->entity->getTimeSlot(),
            'name' => $name,
        ]);

        return $this->entity->getTimeSlot() === $name;
    }
}
