import { Dayjs } from 'dayjs';
import { AvailabilityRule, Uri } from '../../../api/types';

function timeRangesOverlap(
  aStart: string,
  aEnd: string,
  bStart: string,
  bEnd: string,
): boolean {
  // "HH:MM" strings compare correctly lexicographically
  return aStart < bEnd && bStart < aEnd;
}

/**
 * Strict per-day check: for a day of week where the employee has declared at
 * least one availability rule, the shift must fall inside an "available"
 * window and outside every "unavailable" window, or it's a conflict. A day
 * with no rules at all is never flagged — assumed fully available.
 */
export function availabilityConflict(
  rules: AvailabilityRule[],
  userUri: Uri,
  day: Dayjs,
  startTime: string,
  endTime: string,
): boolean {
  const dayOfWeek = day.isoWeekday();
  const userDayRules = rules.filter(
    r => r.user === userUri && r.dayOfWeek === dayOfWeek,
  );

  if (userDayRules.length === 0) {
    return false;
  }

  const unavailable = userDayRules.filter(r => r.type === 'unavailable');
  const available = userDayRules.filter(r => r.type === 'available');

  const overlapsUnavailable = unavailable.some(r =>
    timeRangesOverlap(startTime, endTime, r.startTime, r.endTime),
  );
  if (overlapsUnavailable) {
    return true;
  }

  if (available.length > 0) {
    const coveredByAvailable = available.some(
      r => r.startTime <= startTime && endTime <= r.endTime,
    );
    if (!coveredByAvailable) {
      return true;
    }
  }

  return false;
}
