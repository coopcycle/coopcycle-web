import React from 'react';
import { useTranslation } from 'react-i18next';
import { HolidayRequest } from '../../../api/types';

type Props = {
  holidayRequest: HolidayRequest;
};

export default function HolidayBar({ holidayRequest }: Props) {
  const { t } = useTranslation();

  const isPending = holidayRequest.status === 'pending';

  return (
    <div
      className={`holiday-bar ${isPending ? 'holiday-bar--pending' : ''}`}
      title={holidayRequest.comment || undefined}>
      {isPending
        ? t('SHIFT_PLANNING_HOLIDAY_PENDING')
        : t('SHIFT_PLANNING_HOLIDAY')}
    </div>
  );
}
