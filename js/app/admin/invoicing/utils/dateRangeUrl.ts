import moment, { Moment } from 'moment';

const DATE_AFTER_PARAM = 'after';
const DATE_BEFORE_PARAM = 'before';
const DATE_FORMAT = 'YYYY-MM-DD';

/**
 * Parses ?after=YYYY-MM-DD&before=YYYY-MM-DD from the given query
 * string. Returns null when the params are absent or invalid.
 */
export function dateRangeFromParams(search: string): Moment[] | null {
  const params = new URLSearchParams(search);
  const after = params.get(DATE_AFTER_PARAM);
  const before = params.get(DATE_BEFORE_PARAM);

  if (!after || !before) {
    return null;
  }

  const range = [
    moment(after, DATE_FORMAT, true),
    moment(before, DATE_FORMAT, true),
  ];

  if (!range[0].isValid() || !range[1].isValid()) {
    return null;
  }

  return range;
}

/**
 * Rewrites the current URL's after/before params to the given range,
 * in place (no new history entry), preserving any other query params.
 */
export function syncDateRangeToUrl(dateRange: Moment[]): void {
  const params = new URLSearchParams(window.location.search);
  params.set(DATE_AFTER_PARAM, dateRange[0].format(DATE_FORMAT));
  params.set(DATE_BEFORE_PARAM, dateRange[1].format(DATE_FORMAT));

  window.history.replaceState(
    null,
    '',
    `${window.location.pathname}?${params.toString()}`,
  );
}
