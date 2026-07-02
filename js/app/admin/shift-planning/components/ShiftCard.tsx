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
};

export default function ShiftCard({ shift, onClick, conflictWith }: Props) {
  const { t } = useTranslation();

  return (
    <div
      className="shift-card"
      style={{ backgroundColor: shiftTypeColor(shift.type) }}
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
        </span>
        <span>
          <i className="fa fa-user" aria-hidden="true"></i>{' '}
          {shift.assignments.length}/{shift.slots}
        </span>
      </div>
    </div>
  );
}
