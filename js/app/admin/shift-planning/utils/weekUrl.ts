import dayjs, { Dayjs } from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';

dayjs.extend(isoWeek);

/**
 * Parses ?year=YYYY&week=WW (ISO week) into the Monday of that week.
 * Returns null when the params are absent or invalid.
 */
export function weekFromParams(search: string): Dayjs | null {
  const params = new URLSearchParams(search);
  const year = parseInt(params.get('year') || '', 10);
  const week = parseInt(params.get('week') || '', 10);

  if (!Number.isInteger(year) || !Number.isInteger(week) || week < 1 || week > 53) {
    return null;
  }

  // Jan 4th is always in ISO week 1, so its Monday anchors the ISO year
  return dayjs(`${year}-01-04`)
    .startOf('isoWeek')
    .add(week - 1, 'week');
}

export function weekToParams(weekStart: Dayjs): { year: number; week: number } {
  return { year: weekStart.isoWeekYear(), week: weekStart.isoWeek() };
}

/**
 * Rewrites the current URL's year/week params to the given week, in place
 * (no new history entry), preserving any other query params.
 */
export function syncWeekToUrl(weekStart: Dayjs): void {
  const { year, week } = weekToParams(weekStart);
  const params = new URLSearchParams(window.location.search);
  params.set('year', String(year));
  params.set('week', String(week));

  window.history.replaceState(
    null,
    '',
    `${window.location.pathname}?${params.toString()}`,
  );
}
