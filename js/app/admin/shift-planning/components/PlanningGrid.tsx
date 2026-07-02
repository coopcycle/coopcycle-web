import React from 'react';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { HolidayRequest, PlanningUser, Shift, Uri } from '../../../api/types';
import ShiftCard from './ShiftCard';
import HolidayBar from './HolidayBar';
import { holidayCoversDay, shiftIsOnDay } from '../utils/date';

type Props = {
  weekStart: Dayjs;
  shifts: Shift[];
  holidayRequests: HolidayRequest[];
  users: PlanningUser[];
  onCreate: (day: Dayjs, userUri?: Uri) => void;
  onEdit: (shift: Shift) => void;
};

export default function PlanningGrid({
  weekStart,
  shifts,
  holidayRequests,
  users,
  onCreate,
  onEdit,
}: Props) {
  const { t } = useTranslation();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const openShifts = shifts.filter(s => s.assignments.length < s.slots);

  return (
    <div className="shift-planning__grid-container">
      <div className="shift-planning__grid">
        <div className="shift-planning__header-cell" />
        {days.map(day => (
          <div
            key={day.format('YYYY-MM-DD')}
            className={`shift-planning__header-cell ${
              day.format('YYYY-MM-DD') === today
                ? 'shift-planning__header-cell--today'
                : ''
            }`}>
            {day.format('ddd DD MMM')}
          </div>
        ))}

        <div className="shift-planning__row-label">
          {t('SHIFT_PLANNING_OPEN_SLOTS')}
        </div>
        {days.map(day => (
          <div
            key={day.format('YYYY-MM-DD')}
            className="shift-planning__cell shift-planning__cell--clickable"
            onClick={() => onCreate(day)}>
            {openShifts.filter(s => shiftIsOnDay(s, day)).map(shift => (
              <ShiftCard key={shift['@id']} shift={shift} onClick={onEdit} />
            ))}
          </div>
        ))}

        {users.map(user => {
          const userShifts = shifts.filter(s =>
            s.assignments.some(a => a.user['@id'] === user['@id']),
          );
          const userHolidays = holidayRequests.filter(
            h => h.user['@id'] === user['@id'],
          );

          return (
            <React.Fragment key={user['@id']}>
              <div className="shift-planning__row-label">{user.username}</div>
              {days.map(day => (
                <div
                  key={day.format('YYYY-MM-DD')}
                  className="shift-planning__cell shift-planning__cell--clickable"
                  onClick={() => onCreate(day, user['@id'])}>
                  {userHolidays.filter(h => holidayCoversDay(h, day)).map(h => (
                    <HolidayBar key={h['@id']} holidayRequest={h} />
                  ))}
                  {userShifts.filter(s => shiftIsOnDay(s, day)).map(shift => (
                    <ShiftCard
                      key={shift['@id']}
                      shift={shift}
                      onClick={onEdit}
                    />
                  ))}
                </div>
              ))}
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
}
