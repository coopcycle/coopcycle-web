import React from 'react';
import { Tooltip } from 'antd';
import { PlusOutlined, StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { Shift, ShiftActivity } from '../../../api/types';
import ShiftCard from './ShiftCard';
import { shiftIsOnDay, sortByStart } from '../utils/date';
import { shiftTypeColor } from '../utils/shiftTypeColor';
import { activityDisplayLabel } from '../utils/activityLabel';

function AddShiftButton({ onClick }: { onClick: () => void }) {
  const { t } = useTranslation();

  return (
    <button
      type="button"
      className="shift-planning__add-shift"
      title={t('SHIFT_PLANNING_NEW_SHIFT')}
      onClick={e => {
        e.stopPropagation();
        onClick();
      }}>
      <PlusOutlined />
    </button>
  );
}

type Props = {
  weekStart: Dayjs;
  activities: ShiftActivity[];
  shifts: Shift[];
  onCreate: (day: Dayjs, activity: string) => void;
  onEdit: (shift: Shift) => void;
  bankHolidays?: Record<string, string>;
};

/**
 * Rows = activities (the configured list, not just activities with shifts
 * this week, so a dispatcher can create the first shift of an activity too),
 * columns = days. Cards show assignee usernames since the row no longer
 * implies a user.
 */
export default function ActivityGrid({
  weekStart,
  activities,
  shifts,
  onCreate,
  onEdit,
  bankHolidays,
}: Props) {
  const { t } = useTranslation();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const sortedShifts = sortByStart(shifts);

  return (
    <div className="shift-planning__grid-container">
      <div className="shift-planning__grid">
        <div className="shift-planning__header-cell" />
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

        {activities.map(activity => {
          const activityShifts = sortedShifts.filter(
            s => s.activity === activity.slug,
          );

          return (
            <React.Fragment key={activity.slug}>
              <div className="shift-planning__row-label">
                <span>
                  <span
                    className="shift-type-dot"
                    style={{
                      backgroundColor: activity.color || shiftTypeColor(activity.slug),
                    }}
                  />
                  {activityDisplayLabel(activity, t)}
                </span>
              </div>
              {days.map(day => (
                <div
                  key={day.format('YYYY-MM-DD')}
                  className="shift-planning__cell shift-planning__cell--clickable"
                  onClick={() => onCreate(day, activity.slug)}>
                  {activityShifts
                    .filter(s => shiftIsOnDay(s, day))
                    .map(shift => (
                      <ShiftCard
                        key={shift['@id']}
                        shift={shift}
                        onClick={onEdit}
                        activities={activities}
                        showAssignees
                      />
                    ))}
                  <AddShiftButton onClick={() => onCreate(day, activity.slug)} />
                </div>
              ))}
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
}
