import { Dayjs } from 'dayjs';
import { HolidayRequest, Shift } from '../../../api/types';

export function shiftIsOnDay(shift: Shift, day: Dayjs): boolean {
  return shift.startsAt.slice(0, 10) === day.format('YYYY-MM-DD');
}

// Shift times are always displayed & compared as the wall-clock time sent by
// the API (i.e in the coop's timezone), regardless of the browser's timezone.
// moment is pinned to the coop timezone via moment.tz.setDefault(), but dayjs
// & Date are not, so we strip the UTC offset before parsing.

export function wallClock(iso: string): string {
  return iso.slice(0, 19);
}

export function wallClockTime(iso: string): string {
  return iso.slice(11, 16);
}

export function sortByStart(shifts: Shift[]): Shift[] {
  return [...shifts].sort(
    (a, b) =>
      wallClock(a.startsAt).localeCompare(wallClock(b.startsAt)) ||
      wallClock(a.endsAt).localeCompare(wallClock(b.endsAt)),
  );
}

export function rangesOverlap(
  aStart: string,
  aEnd: string,
  bStart: string,
  bEnd: string,
): boolean {
  return aStart < bEnd && bStart < aEnd;
}

export function findOverlappingShift(
  shift: Shift,
  others: Shift[],
): Shift | undefined {
  return others.find(
    other =>
      other['@id'] !== shift['@id'] &&
      rangesOverlap(
        wallClock(shift.startsAt),
        wallClock(shift.endsAt),
        wallClock(other.startsAt),
        wallClock(other.endsAt),
      ),
  );
}

export function holidayCoversDay(
  holidayRequest: HolidayRequest,
  day: Dayjs,
): boolean {
  const d = day.format('YYYY-MM-DD');

  return (
    d >= holidayRequest.startDate.slice(0, 10) &&
    d <= holidayRequest.endDate.slice(0, 10)
  );
}
