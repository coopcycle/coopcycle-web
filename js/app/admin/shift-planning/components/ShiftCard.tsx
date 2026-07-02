import React from 'react';
import { Shift } from '../../../api/types';
import { shiftTypeColor } from '../utils/shiftTypeColor';
import moment from 'moment';

type Props = {
  shift: Shift;
  onClick?: (shift: Shift) => void;
};

export default function ShiftCard({ shift, onClick }: Props) {
  return (
    <div
      className="shift-card"
      style={{ backgroundColor: shiftTypeColor(shift.type) }}
      onClick={e => {
        e.stopPropagation();
        onClick && onClick(shift);
      }}>
      <div className="shift-card__type">{shift.type}</div>
      <div className="shift-card__meta">
        <span>
          {moment(shift.startsAt).format('HH:mm')}
          {' - '}
          {moment(shift.endsAt).format('HH:mm')}
        </span>
        <span>
          <i className="fa fa-user" aria-hidden="true"></i>{' '}
          {shift.assignments.length}/{shift.slots}
        </span>
      </div>
    </div>
  );
}
