import React from 'react';
import { Tooltip } from 'antd';
import { StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { Shift } from '../../../api/types';
import ShiftCard from './ShiftCard';
import { layoutOverlapping, minutesFromMidnight, shiftIsOnDay } from '../utils/date';

const HOUR_HEIGHT = 48;
const MIN_BLOCK_HEIGHT = 20;
const HOURS = [...Array(24)].map((_, h) => h);

type Props = {
  weekStart: Dayjs;
  shifts: Shift[];
  onCreate: (day: Dayjs, time: Dayjs) => void;
  onEdit: (shift: Shift) => void;
  typeColors?: Record<string, string>;
  bankHolidays?: Record<string, string>;
};

/**
 * X = days, Y = hours of the day. Shifts are positioned/sized by their
 * actual start/end time; overlapping shifts on the same day share width
 * side by side (see utils/date.ts#layoutOverlapping).
 */
export default function CalendarGrid({
  weekStart,
  shifts,
  onCreate,
  onEdit,
  typeColors,
  bankHolidays,
}: Props) {
  const { t } = useTranslation();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const onColumnClick = (
    day: Dayjs,
    e: React.MouseEvent<HTMLDivElement>,
  ) => {
    const rect = e.currentTarget.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const rawMinutes = (offsetY / HOUR_HEIGHT) * 60;
    const snapped = Math.min(
      23 * 60 + 30,
      Math.max(0, Math.round(rawMinutes / 30) * 30),
    );
    const time = day.hour(Math.floor(snapped / 60)).minute(snapped % 60);
    onCreate(day, time);
  };

  return (
    <div className="shift-planning-calendar__container">
      <div className="shift-planning-calendar">
        <div className="shift-planning-calendar__header">
          <div className="shift-planning-calendar__hours-header" />
          {days.map(day => {
            const dayKey = day.format('YYYY-MM-DD');
            const holidayName = bankHolidays?.[dayKey];

            return (
              <div
                key={dayKey}
                className={`shift-planning__header-cell ${
                  dayKey === today ? 'shift-planning__header-cell--today' : ''
                } ${holidayName ? 'shift-planning__header-cell--holiday' : ''}`}>
                {holidayName ? (
                  <Tooltip
                    title={t('SHIFT_PLANNING_BANK_HOLIDAY', {
                      name: holidayName,
                    })}>
                    <StarFilled className="shift-planning__holiday-icon" />
                    {day.format('ddd DD MMM')}
                  </Tooltip>
                ) : (
                  day.format('ddd DD MMM')
                )}
              </div>
            );
          })}
        </div>
        <div className="shift-planning-calendar__body">
          <div className="shift-planning-calendar__hours">
            {HOURS.map(h => (
              <div
                key={h}
                className="shift-planning-calendar__hour-label"
                style={{ height: HOUR_HEIGHT }}>
                {String(h).padStart(2, '0')}:00
              </div>
            ))}
          </div>
          {days.map(day => {
            const dayShifts = shifts.filter(s => shiftIsOnDay(s, day));
            const laidOut = layoutOverlapping(dayShifts);

            return (
              <div
                key={day.format('YYYY-MM-DD')}
                className="shift-planning-calendar__day-column"
                style={{
                  height: HOUR_HEIGHT * 24,
                  backgroundSize: `100% ${HOUR_HEIGHT}px`,
                }}
                onClick={e => onColumnClick(day, e)}>
                {laidOut.map(({ shift, col, cols }) => {
                  const top =
                    (minutesFromMidnight(shift.startsAt) / 60) * HOUR_HEIGHT;
                  const height = Math.max(
                    MIN_BLOCK_HEIGHT,
                    ((minutesFromMidnight(shift.endsAt) -
                      minutesFromMidnight(shift.startsAt)) /
                      60) *
                      HOUR_HEIGHT,
                  );

                  return (
                    <div
                      key={shift['@id']}
                      className="shift-planning-calendar__block"
                      style={{
                        top,
                        height,
                        left: `${(col / cols) * 100}%`,
                        width: `${(1 / cols) * 100}%`,
                      }}>
                      <ShiftCard
                        shift={shift}
                        onClick={onEdit}
                        typeColors={typeColors}
                        showAssignees
                      />
                    </div>
                  );
                })}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
