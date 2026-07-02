import { Dayjs } from 'dayjs';
import { HolidayRequest, Shift } from '../../../api/types';

export function shiftIsOnDay(shift: Shift, day: Dayjs): boolean {
  return shift.startsAt.slice(0, 10) === day.format('YYYY-MM-DD');
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
