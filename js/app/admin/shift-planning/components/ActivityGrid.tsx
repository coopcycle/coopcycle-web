import React, { useCallback } from 'react';
import { App, Tooltip } from 'antd';
import { PlusOutlined, StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { usePutShiftMutation } from '../../../api/slice';
import { Shift, ShiftActivity } from '../../../api/types';
import ShiftCard from './ShiftCard';
import { CellData, DraggableCard, DropCell, useCellDropMonitor } from './DragDrop';
import { shiftIsOnDay, sortByStart, withDay } from '../utils/date';
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
  const { message } = App.useApp();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const sortedShifts = sortByStart(shifts);

  const [putShift] = usePutShiftMutation();

  // Dropping a card on a different day moves the shift there (keeping its
  // wall-clock time); dropping on a different activity row retypes it
  // instead. Both can happen from a single drag.
  const onMove = useCallback(
    async (source: CellData, destination: CellData) => {
      const { shiftUri, activity: fromActivity, dayKey: fromDayKey } = source;
      const { activity: toActivity, dayKey: toDayKey } = destination;
      if (fromActivity === toActivity && fromDayKey === toDayKey) {
        return;
      }

      const shift = shifts.find(s => s['@id'] === shiftUri);
      if (!shift) {
        return;
      }

      try {
        await putShift({
          '@id': shift['@id'],
          activity: toActivity,
          startsAt: withDay(shift.startsAt, toDayKey),
          endsAt: withDay(shift.endsAt, toDayKey),
          slots: shift.slots,
          breakMinutes: shift.breakMinutes,
          comment: shift.comment,
          users: shift.assignments.map(a => a.user['@id']),
        }).unwrap();
        message.success(t('SHIFT_PLANNING_SAVED'));
      } catch {
        message.error(t('SHIFT_PLANNING_ERROR'));
      }
    },
    [shifts, putShift, message, t],
  );
  useCellDropMonitor(onMove);

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
              {days.map(day => {
                const dayKey = day.format('YYYY-MM-DD');

                return (
                  <DropCell
                    key={dayKey}
                    data={{ activity: activity.slug, dayKey }}
                    className="shift-planning__cell shift-planning__cell--clickable"
                    onClick={() => onCreate(day, activity.slug)}>
                    {activityShifts
                      .filter(s => shiftIsOnDay(s, day))
                      .map(shift => (
                        <DraggableCard
                          key={shift['@id']}
                          data={{ shiftUri: shift['@id'], activity: activity.slug, dayKey }}>
                          <ShiftCard
                            shift={shift}
                            onClick={onEdit}
                            activities={activities}
                            showAssignees
                            allowDuplicate
                          />
                        </DraggableCard>
                      ))}
                    <AddShiftButton onClick={() => onCreate(day, activity.slug)} />
                  </DropCell>
                );
              })}
            </React.Fragment>
          );
        })}
      </div>
    </div>
  );
}
