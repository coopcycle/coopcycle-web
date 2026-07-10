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

export function minutesFromMidnight(iso: string): number {
  const [h, m] = wallClockTime(iso).split(':').map(Number);
  return h * 60 + m;
}

export type CalendarLayoutItem = { shift: Shift; col: number; cols: number };

/**
 * Lays shifts of a single day out into side-by-side columns, like a
 * calendar day view: overlapping shifts share width, non-overlapping ones
 * each get the full width. Two-pass algorithm — greedily pack into the
 * first free column (sorted by start time), then re-group into connected
 * overlap clusters to size each cluster's shared column count.
 */
export function layoutOverlapping(shifts: Shift[]): CalendarLayoutItem[] {
  const sorted = sortByStart(shifts);

  // Pass 1: assign each shift to the first column whose last shift has
  // already ended by this shift's start time.
  const columnEnds: string[] = [];
  const placements = sorted.map(shift => {
    const start = wallClock(shift.startsAt);
    const end = wallClock(shift.endsAt);
    let col = columnEnds.findIndex(colEnd => colEnd <= start);
    if (col === -1) {
      col = columnEnds.length;
      columnEnds.push(end);
    } else {
      columnEnds[col] = end;
    }
    return { shift, col, start, end };
  });

  // Pass 2: group into connected overlap clusters (sorted by start, a new
  // cluster starts whenever a shift begins at/after every prior shift in
  // the current cluster has ended) and size each cluster by its max column.
  const result: CalendarLayoutItem[] = [];
  let clusterStart = 0;
  let clusterMaxEnd = '';

  const flush = (from: number, to: number) => {
    const clusterCols =
      Math.max(...placements.slice(from, to).map(p => p.col)) + 1;
    for (let j = from; j < to; j++) {
      result.push({
        shift: placements[j].shift,
        col: placements[j].col,
        cols: clusterCols,
      });
    }
  };

  placements.forEach((p, i) => {
    if (i > clusterStart && p.start >= clusterMaxEnd) {
      flush(clusterStart, i);
      clusterStart = i;
      clusterMaxEnd = p.end;
    } else {
      clusterMaxEnd = clusterMaxEnd > p.end ? clusterMaxEnd : p.end;
    }
  });
  if (clusterStart < placements.length) {
    flush(clusterStart, placements.length);
  }

  return result;
}
