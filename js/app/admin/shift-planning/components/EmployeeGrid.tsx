import React, { useState } from 'react';
import { Button, Select, Tooltip } from 'antd';
import { CloseOutlined, PlusOutlined, StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { HolidayRequest, PlanningUser, Shift, Uri } from '../../../api/types';
import Avatar from '../../../components/Avatar';
import ShiftCard from './ShiftCard';
import OpenSlotCard from './OpenSlotCard';
import HolidayBar from './HolidayBar';
import {
  findOverlappingShift,
  holidayCoversDay,
  shiftIsOnDay,
  sortByStart,
} from '../utils/date';

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
  holidayRequests: HolidayRequest[];
  users: PlanningUser[];
  allUsers: PlanningUser[];
  onCreate: (day: Dayjs, userUri?: Uri) => void;
  onEdit: (shift: Shift) => void;
  onAddUser: (userUri: Uri) => void;
  onRemoveUser: (userUri: Uri) => void;
  typeColors?: Record<string, string>;
  /** Map of "YYYY-MM-DD" -> bank holiday name, for the day header highlight */
  bankHolidays?: Record<string, string>;
};

export default function EmployeeGrid({
  weekStart,
  shifts,
  holidayRequests,
  users,
  allUsers,
  onCreate,
  onEdit,
  onAddUser,
  onRemoveUser,
  typeColors,
  bankHolidays,
}: Props) {
  const { t } = useTranslation();

  const [isAdding, setIsAdding] = useState(false);

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const sortedShifts = sortByStart(shifts);
  const openShifts = sortedShifts.filter(s => s.assignments.length < s.slots);

  const visibleUris = new Set(users.map(u => u['@id']));
  const candidates = allUsers.filter(u => !visibleUris.has(u['@id']));

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
                typeColors={typeColors}
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
                      conflictWith={findOverlappingShift(shift, userShifts)}
                      typeColors={typeColors}
                    />
                  ))}
                  <AddShiftButton
                    onClick={() => onCreate(day, user['@id'])}
                  />
                </div>
              ))}
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
