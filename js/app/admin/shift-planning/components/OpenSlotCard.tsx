import React from 'react';
import { useTranslation } from 'react-i18next';
import { Shift, ShiftActivity } from '../../../api/types';
import { activityColor } from '../utils/shiftTypeColor';
import { activityLabel } from '../utils/activityLabel';
import { wallClockTime } from '../utils/date';

type Props = {
  shift: Shift;
  onClick?: (shift: Shift) => void;
  activities?: ShiftActivity[];
};

/**
 * A "ghost" card representing the unfilled capacity of a shift,
 * shown in the "Open slots" row of the planning grid.
 */
export default function OpenSlotCard({ shift, onClick, activities }: Props) {
  const { t } = useTranslation();

  const remaining = shift.slots - shift.assignments.length;

  return (
    <div
      className="open-slot-card"
      onClick={e => {
        e.stopPropagation();
        onClick && onClick(shift);
      }}>
      <div className="shift-card__type">
        <span>
          <span
            className="shift-type-dot"
            style={{ backgroundColor: activityColor(shift.activity, activities) }}
          />
          {activityLabel(shift.activity, activities, t)}
        </span>
      </div>
      <div className="shift-card__meta">
        <span>
          {wallClockTime(shift.startsAt)}
          {' - '}
          {wallClockTime(shift.endsAt)}
        </span>
        <span>{t('SHIFT_PLANNING_SLOTS_TO_FILL', { count: remaining })}</span>
      </div>
    </div>
  );
}
