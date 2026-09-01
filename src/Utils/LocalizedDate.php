<?php

namespace AppBundle\Utils;

use Carbon\Carbon;

/**
 * Formats a date in a human readable way, relatively to the current date,
 * i.e "Today at 10:00 AM", "Tomorrow at 10:00 AM", …
 */
final class LocalizedDate
{
    public static function format(\DateTimeInterface $date, string $locale): string
    {
        $c = Carbon::instance($date)->locale($locale);

        if ($c->isToday()) {
            return $c->isoFormat('[Today at] LT');
        }

        if ($c->isTomorrow()) {
            return $c->isoFormat('[Tomorrow at] LT');
        }

        if ($c->isYesterday()) {
            return $c->isoFormat('[Yesterday at] LT');
        }

        if ($c->isFuture() && $c->diffInDays() < 7) {
            return $c->isoFormat('dddd [at] LT');
        }

        if ($c->isPast() && Carbon::now()->diffInDays($c) < 7) {
            return $c->isoFormat('[Last] dddd [at] LT');
        }

        return $c->isoFormat('L');
    }
}
