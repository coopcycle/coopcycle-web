import React, { useCallback, useMemo, useState } from 'react';
import { App, Button, Select, Tooltip } from 'antd';
import { CloseOutlined, PlusOutlined, StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetEmployeeProfilesQuery, usePutShiftMutation } from '../../../api/slice';
import {
  HolidayRequest,
  PlanningUser,
  Shift,
  ShiftActivity,
  Uri,
} from '../../../api/types';
import Avatar from '../../../components/Avatar';
import ShiftCard from './ShiftCard';
import OpenSlotCard from './OpenSlotCard';
import HolidayBar from './HolidayBar';
import { CellData, DraggableCard, DropCell, useCellDropMonitor } from './DragDrop';
import {
  findOverlappingShift,
  holidayCoversDay,
  netHours,
  shiftIsOnDay,
  sortByStart,
  withDay,
} from '../utils/date';

// 7.5 -> "7.5h", 12 -> "12h"
const fmtHours = (hours: number): string =>
  `${(Math.round(hours * 10) / 10).toString()}h`;

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
  shifts: Shift[];
  /** Unfiltered shifts of the week: hour totals must ignore the type filter */
  allShifts?: Shift[];
  holidayRequests: HolidayRequest[];
  users: PlanningUser[];
  allUsers: PlanningUser[];
  onCreate: (day: Dayjs, userUri?: Uri) => void;
  onEdit: (shift: Shift) => void;
  onAddUser: (userUri: Uri) => void;
  onRemoveUser: (userUri: Uri) => void;
  activities?: ShiftActivity[];
  /** Map of "YYYY-MM-DD" -> bank holiday name, for the day header highlight */
  bankHolidays?: Record<string, string>;
};

export default function EmployeeGrid({
  weekStart,
  shifts,
  allShifts,
  holidayRequests,
  users,
  allUsers,
  onCreate,
  onEdit,
  onAddUser,
  onRemoveUser,
  activities,
  bankHolidays,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [isAdding, setIsAdding] = useState(false);
  const [putShift] = usePutShiftMutation();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const sortedShifts = sortByStart(shifts);
  const openShifts = sortedShifts.filter(s => s.assignments.length < s.slots);

  // Each user's contracted weekly hours (from their HR profile), shown as
  // the denominator next to their planned hours
  const { data: profiles } = useGetEmployeeProfilesQuery();
  const contractedHoursByUser = useMemo(() => {
    const map = new Map<Uri, number>();
    (profiles ?? []).forEach(profile => {
      const hours = profile.weeklyContractedHours
        ? parseFloat(profile.weeklyContractedHours)
        : NaN;
      if (!Number.isNaN(hours) && hours > 0) {
        map.set(profile.user, hours);
      }
    });
    return map;
  }, [profiles]);

  // Planned net hours & reported overtime per user, over the whole week
  // (ignores the type filter, so the totals stay true while filtering)
  const hoursByUser = new Map<Uri, { planned: number; overtime: number }>();
  (allShifts ?? shifts).forEach(shift => {
    const planned = netHours(shift.startsAt, shift.endsAt, shift.breakMinutes);
    shift.assignments.forEach(a => {
      const entry = hoursByUser.get(a.user['@id']) ?? {
        planned: 0,
        overtime: 0,
      };
      entry.planned += planned;
      if (a.adjustment) {
        entry.overtime +=
          netHours(
            a.adjustment.startsAt,
            a.adjustment.endsAt,
            a.adjustment.breakMinutes,
          ) - planned;
      }
      hoursByUser.set(a.user['@id'], entry);
    });
  });

  const visibleUris = new Set(users.map(u => u['@id']));
  const candidates = allUsers.filter(u => !visibleUris.has(u['@id']));

  // Dropping a card on a different day moves the shift there (keeping its
  // wall-clock time); dropping on a different rider's row reassigns it to
  // that rider instead. Both can happen from a single drag.
  const onMove = useCallback(
    async (source: CellData, destination: CellData) => {
      const { shiftUri, userUri: fromUserUri, dayKey: fromDayKey } = source;
      const { userUri: toUserUri, dayKey: toDayKey } = destination;
      if (fromUserUri === toUserUri && fromDayKey === toDayKey) {
        return;
      }

      const shift = shifts.find(s => s['@id'] === shiftUri);
      if (!shift) {
        return;
      }

      const newUsers =
        toUserUri === fromUserUri
          ? shift.assignments.map(a => a.user['@id'])
          : shift.assignments.map(a =>
              a.user['@id'] === fromUserUri ? (toUserUri as Uri) : a.user['@id'],
            );

      try {
        await putShift({
          '@id': shift['@id'],
          activity: shift.activity,
          startsAt: withDay(shift.startsAt, toDayKey),
          endsAt: withDay(shift.endsAt, toDayKey),
          slots: shift.slots,
          breakMinutes: shift.breakMinutes,
          comment: shift.comment,
          users: newUsers,
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

        <div className="shift-planning__row-label shift-planning__row-label--open-slots">
          <span>{t('SHIFT_PLANNING_OPEN_SLOTS')}</span>
        </div>
        {days.map(day => (
          <div
            key={day.format('YYYY-MM-DD')}
            className="shift-planning__cell shift-planning__cell--clickable shift-planning__cell--open-slots"
            onClick={() => onCreate(day)}>
            {openShifts.filter(s => shiftIsOnDay(s, day)).map(shift => (
              <OpenSlotCard
                key={shift['@id']}
                shift={shift}
                onClick={onEdit}
                activities={activities}
              />
            ))}
            <AddShiftButton onClick={() => onCreate(day)} />
          </div>
        ))}

        {users.map(user => {
          const userShifts = sortedShifts.filter(s =>
            s.assignments.some(a => a.user['@id'] === user['@id']),
          );
          const userHolidays = holidayRequests.filter(
            h => h.user['@id'] === user['@id'],
          );
          const hasShifts = userShifts.length > 0;

          const fullName = [user.givenName, user.familyName]
            .filter(Boolean)
            .join(' ');

          const hours = hoursByUser.get(user['@id']) ?? {
            planned: 0,
            overtime: 0,
          };
          const contractedHours = contractedHoursByUser.get(user['@id']);
          const overLimit =
            contractedHours !== undefined && hours.planned > contractedHours;
          const hasOvertime = Math.round(hours.overtime * 10) !== 0;

          return (
            <React.Fragment key={user['@id']}>
              <div className="shift-planning__row-label">
                <span className="shift-planning__user">
                  <Avatar username={user.username} size="24" />
                  <span className="shift-planning__user-names">
                    <span>{user.username}</span>
                    {fullName && (
                      <span className="shift-planning__user-fullname">
                        {fullName}
                      </span>
                    )}
                    <span className="shift-planning__user-hours">
                      <Tooltip
                        title={
                          contractedHours !== undefined
                            ? t('SHIFT_PLANNING_WEEK_HOURS_TOOLTIP', {
                                max: fmtHours(contractedHours),
                              })
                            : t('SHIFT_PLANNING_WEEK_HOURS_TOOLTIP_NO_LIMIT')
                        }>
                        <span
                          className={
                            overLimit
                              ? 'shift-planning__user-hours--over'
                              : undefined
                          }>
                          {fmtHours(hours.planned)}
                          {contractedHours !== undefined &&
                            `/${fmtHours(contractedHours)}`}
                        </span>
                      </Tooltip>
                      {hasOvertime && (
                        <Tooltip title={t('SHIFT_PLANNING_OVERTIME_TOOLTIP')}>
                          <span
                            className={
                              hours.overtime > 0
                                ? 'shift-planning__user-overtime'
                                : 'shift-planning__user-overtime shift-planning__user-overtime--negative'
                            }>
                            {hours.overtime > 0 ? '+' : ''}
                            {fmtHours(hours.overtime)}
                          </span>
                        </Tooltip>
                      )}
                    </span>
                  </span>
                </span>
                <Button
                  type="text"
                  size="small"
                  icon={<CloseOutlined />}
                  disabled={hasShifts}
                  title={
                    hasShifts
                      ? t('SHIFT_PLANNING_REMOVE_DISABLED')
                      : t('SHIFT_PLANNING_REMOVE')
                  }
                  onClick={() => onRemoveUser(user['@id'])}
                />
              </div>
              {days.map(day => {
                const dayKey = day.format('YYYY-MM-DD');

                return (
                  <DropCell
                    key={dayKey}
                    data={{ userUri: user['@id'], dayKey }}
                    className="shift-planning__cell shift-planning__cell--clickable"
                    onClick={() => onCreate(day, user['@id'])}>
                    {userHolidays.filter(h => holidayCoversDay(h, day)).map(h => (
                      <HolidayBar key={h['@id']} holidayRequest={h} />
                    ))}
                    {userShifts.filter(s => shiftIsOnDay(s, day)).map(shift => (
                      <DraggableCard
                        key={shift['@id']}
                        data={{ shiftUri: shift['@id'], userUri: user['@id'], dayKey }}>
                        <ShiftCard
                          shift={shift}
                          onClick={onEdit}
                          conflictWith={findOverlappingShift(shift, userShifts)}
                          activities={activities}
                          allowDuplicate
                        />
                      </DraggableCard>
                    ))}
                    <AddShiftButton
                      onClick={() => onCreate(day, user['@id'])}
                    />
                  </DropCell>
                );
              })}
            </React.Fragment>
          );
        })}

        <div className="shift-planning__row-label">
          {isAdding ? (
            <Select
              autoFocus
              defaultOpen
              showSearch
              size="small"
              style={{ width: '100%' }}
              placeholder={t('SHIFT_PLANNING_ADD_USER')}
              optionFilterProp="label"
              options={candidates.map(u => ({
                value: u['@id'],
                label: u.username,
              }))}
              onChange={(uri: Uri) => {
                onAddUser(uri);
                setIsAdding(false);
              }}
              onBlur={() => setIsAdding(false)}
            />
          ) : (
            <Button
              type="dashed"
              size="small"
              block
              icon={<PlusOutlined />}
              disabled={candidates.length === 0}
              onClick={() => setIsAdding(true)}>
              {t('SHIFT_PLANNING_ADD_USER')}
            </Button>
          )}
        </div>
        {days.map(day => (
          <div key={day.format('YYYY-MM-DD')} className="shift-planning__cell" />
        ))}
      </div>
    </div>
  );
}
