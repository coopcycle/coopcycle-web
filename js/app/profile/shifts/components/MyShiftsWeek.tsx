import React, { useState } from 'react';
import { Empty, Spin } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetMyShiftsQuery } from '../../../api/slice';
import WeekNavigator from '../../../admin/shift-planning/components/WeekNavigator';
import ShiftCard from '../../../admin/shift-planning/components/ShiftCard';
import { shiftIsOnDay } from '../../../admin/shift-planning/utils/date';

export default function MyShiftsWeek() {
  const { t } = useTranslation();

  const [weekStart, setWeekStart] = useState<Dayjs>(() =>
    dayjs().startOf('isoWeek'),
  );

  const { data, isFetching } = useGetMyShiftsQuery({
    after: weekStart.format('YYYY-MM-DD'),
    before: weekStart.add(6, 'day').format('YYYY-MM-DD'),
  });

  const shifts = data?.['hydra:member'] ?? [];
  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));

  return (
    <div>
      <div className="mb-3">
        <WeekNavigator value={weekStart} onChange={setWeekStart} />
      </div>
      <Spin spinning={isFetching}>
        {shifts.length === 0 ? (
          <Empty description={t('SHIFT_PLANNING_NO_SHIFTS')} />
        ) : (
          days.map(day => {
            const dayShifts = shifts.filter(s => shiftIsOnDay(s, day));
            if (dayShifts.length === 0) {
              return null;
            }
            return (
              <div key={day.format('YYYY-MM-DD')} className="mb-3">
                <strong>{day.format('dddd DD MMM')}</strong>
                {dayShifts.map(shift => (
                  <ShiftCard key={shift['@id']} shift={shift} />
                ))}
              </div>
            );
          })
        )}
      </Spin>
    </div>
  );
}
