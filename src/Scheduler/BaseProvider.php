<?php

namespace AppBundle\Scheduler;

use AppBundle\Message\CalculateTaskListsDistance;
use AppBundle\Message\ResetRushMode;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class BaseProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(
        private readonly LockFactory $lockFactory,
        private readonly CacheInterface $schedulerCache,
        private readonly string $databaseName,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->add(
                RecurringMessage::cron('@midnight', new ResetRushMode()),
                // Outside service hours: one routing call per task list of the day,
                // and the figures are read by an external job two days later, so
                // there is nothing to gain from running it any earlier.
                RecurringMessage::cron('17 3 * * *', new CalculateTaskListsDistance()),
                // Campaigns are scheduled to the minute by admins, so this has to
                // run often enough that "scheduled for 9am" means roughly 9am.
                // The command only works out the audience; the sending itself is
                // one queued message per recipient.
                RecurringMessage::cron('*/5 * * * *', new RunCommandMessage('coopcycle:marketing:send-due-campaigns')),
                // The maintenance sweeps are idempotent and nobody is waiting on
                // them, so they run in the quiet hours, spaced out to keep them
                // off each other's toes.
                RecurringMessage::cron('23 4 * * *', new RunCommandMessage('coopcycle:referral:expire')),
                RecurringMessage::cron('31 4 * * *', new RunCommandMessage('coopcycle:loyalty:expire-points')),
                // Cards lapse a year after purchase by default, so this
                // has work to do on most instances. Setting the policy to
                // "never" -- which some jurisdictions require -- makes it a
                // no-op.
                RecurringMessage::cron('37 4 * * *', new RunCommandMessage('coopcycle:gift-cards:expire')),
                // Hourly rather than nightly: this is a restaurant waiting to
                // be paid, and a dropped webhook shouldn't cost them a day.
                RecurringMessage::cron('43 * * * *', new RunCommandMessage('coopcycle:gift-cards:settle-pending')),
                // A backstop for the suppression webhooks Postmark couldn't deliver.
                RecurringMessage::cron('47 4 * * *', new RunCommandMessage('coopcycle:marketing:sync-suppressions')),
            )
            // Without this, two workers consuming the schedule would each get
            // their own trigger, and a campaign would go out twice.
            // The lock store is shared by every tenant, so the key has to carry
            // the tenant name: otherwise the first tenant to start holds
            // "scheduler-default" and all the others never fire.
            ->lock($this->lockFactory->createLock(sprintf('%s:scheduler-default', $this->databaseName)))
            // Without this, a worker restart recomputes the schedule from "now",
            // and whatever was due while it was down never runs.
            ->stateful($this->schedulerCache);
    }
}
