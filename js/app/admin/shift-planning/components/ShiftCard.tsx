import React from 'react';
import { Tooltip } from 'antd';
import { WarningFilled } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { Shift } from '../../../api/types';
import { shiftTypeColor } from '../utils/shiftTypeColor';
import { wallClockTime } from '../utils/date';

type Props = {
  shift: Shift;
  onClick?: (shift: Shift) => void;
  conflictWith?: Shift;
  typeColors?: Record<string, string>;
  /** Show assignee usernames on the card, for views where the row doesn't already imply the user (type/calendar views) */
  showAssignees?: boolean;
};

export default function ShiftCard({
  shift,
  onClick,
  conflictWith,
  typeColors,
  showAssignees,
}: Props) {
  const { t } = useTranslation();

  return (
    <div
      className="shift-card"
      style={{ backgroundColor: shiftTypeColor(shift.type, typeColors) }}
      onClick={e => {
        e.stopPropagation();
        onClick && onClick(shift);
      }}>
      <div className="shift-card__type">
        {shift.type}
        {conflictWith && (
          <Tooltip
            title={t('SHIFT_PLANNING_OVERLAP_TOOLTIP', {
              type: conflictWith.type,
              start: wallClockTime(conflictWith.startsAt),
              end: wallClockTime(conflictWith.endsAt),
            })}>
            <WarningFilled className="shift-card__conflict" />
          </Tooltip>
        )}
      </div>
      <div className="shift-card__meta">
        <span>
          {wallClockTime(shift.startsAt)}
          {' - '}
          {wallClockTime(shift.endsAt)}
          {shift.breakMinutes > 0 && (
            <Tooltip
              title={t('SHIFT_PLANNING_BREAK_MINUTES_TOOLTIP', {
                minutes: shift.breakMinutes,
              })}>
              {' '}
              <i className="fa fa-coffee" aria-hidden="true"></i>
            </Tooltip>
          )}
        </span>
        <span
          className={
            shift.assignments.length > shift.slots
              ? 'shift-card__count--over'
              : undefined
          }>
          <i className="fa fa-user" aria-hidden="true"></i>{' '}
          {shift.assignments.length}/{shift.slots}
        </span>
      </div>
      {showAssignees && shift.assignments.length > 0 && (
        <div className="shift-card__assignees">
          {shift.assignments.map(a => a.user.username).join(', ')}
        </div>
      )}
      {shift.requiredSkills.length > 0 && (
        <div className="shift-card__skills">
          {shift.requiredSkills.map(s => (
            <span key={s['@id']} className="shift-card__skill">
              {s.name}
            </span>
          ))}
        </div>
      )}
      {shift.comment && (
        <div className="shift-card__comment" title={shift.comment}>
          {shift.comment}
        </div>
      )}
    </div>
  );
}
