<?php

namespace AppBundle\Message;

/**
 * Compute travelled distance and CO2 emissions for every task list of a day.
 *
 * Runs on a schedule rather than on each completion. The figures are read by an
 * external reporting job a couple of days later, so they do not need to be
 * accurate by the minute -- and computing them per completion was both wasteful
 * and wrong: the route was rebuilt over the whole list every time a task was
 * ticked off, and it was measured against the list *as it stood at that moment*,
 * so a task finished early was measured against a half-built list and never
 * recomputed once the rest was assigned.
 */
final class CalculateTaskListsDistance
{
    public function __construct(
        /**
         * The day to recompute. Null means the day before the run, which is the
         * last one whose task lists are settled.
         */
        public readonly ?string $date = null,
    ) {}
}
