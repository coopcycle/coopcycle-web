<?php

namespace AppBundle\Scheduler;

use AppBundle\Message\CalculateTaskListsDistance;
use AppBundle\Message\ResetRushMode;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule]
class BaseProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(
            RecurringMessage::cron('@midnight', new ResetRushMode()),
            // Outside service hours: one routing call per task list of the day,
            // and the figures are read by an external job two days later, so
            // there is nothing to gain from running it any earlier.
            RecurringMessage::cron('17 3 * * *', new CalculateTaskListsDistance())
        );
    }
}

