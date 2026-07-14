import React, { useState } from 'react';
import { Button, Empty, Spin, Tooltip } from 'antd';
import { FieldTimeOutlined, StarFilled } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import {
  useGetBankHolidaysQuery,
  useGetMeQuery,
  useGetMyShiftsQuery,
  useGetShiftSettingsQuery,
} from '../../../api/slice';
import WeekNavigator from '../../../admin/shift-planning/components/WeekNavigator';
import ShiftCard from '../../../admin/shift-planning/components/ShiftCard';
import ReportTimeModal from '../../../admin/shift-planning/components/ReportTimeModal';
import OpenShiftsWeek from './OpenShiftsWeek';
import {
  shiftIsOnDay,
  sortByStart,
  wallClockTime,
} from '../../../admin/shift-planning/utils/date';
import { Shift } from '../../../api/types';

export default function MyShiftsWeek() {
  const { t } = useTranslation();

  const [weekStart, setWeekStart] = useState<Dayjs>(() =>
    dayjs().startOf('isoWeek'),
  );

  const after = weekStart.format('YYYY-MM-DD');
  const before = weekStart.add(6, 'day').format('YYYY-MM-DD');

  const { data, isFetching } = useGetMyShiftsQuery({ after, before });
  const { data: me } = useGetMeQuery();
  const [reportShift, setReportShift] = useState<Shift | null>(null);
  const { data: shiftSettings } = useGetShiftSettingsQuery();
  const { data: bankHolidaysData } = useGetBankHolidaysQuery({
    after,
    before,
  });

  const bankHolidays: Record<string, string> = {};
  (bankHolidaysData?.holidays ?? []).forEach(h => {
    bankHolidays[h.date] = h.name;
  });

  const shifts = sortByStart(data?.['hydra:member'] ?? []);
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
            const dayKey = day.format('YYYY-MM-DD');
            const holidayName = bankHolidays[dayKey];
            return (
              <div key={dayKey} className="mb-3">
                <strong>
                  {holidayName && (
                    <Tooltip
                      title={t('SHIFT_PLANNING_BANK_HOLIDAY', {
                        name: holidayName,
                      })}>
                      <StarFilled className="shift-planning__holiday-icon" />
                    </Tooltip>
                  )}
                  {day.format('dddd DD MMM')}
                </strong>
                {dayShifts.map(shift => {
                  const adjustment = shift.assignments.find(
                    a => a.user.username === me?.username,
                  )?.adjustment;
                  return (
                    <div key={shift['@id']}>
                      <ShiftCard
                        shift={shift}
                        typeColors={shiftSettings?.typeColors}
                      />
                      <div className="my-shift-actual">
                        {adjustment && (
                          <span className="my-shift-actual__summary">
                            {t('SHIFT_TIME_REPORT_SUMMARY', {
                              start: wallClockTime(adjustment.startsAt),
                              end: wallClockTime(adjustment.endsAt),
                              break: adjustment.breakMinutes,
                            })}
                          </span>
                        )}
                        <Button
                          type="link"
                          size="small"
                          icon={<FieldTimeOutlined />}
                          onClick={() => setReportShift(shift)}>
                          {adjustment
                            ? t('SHIFT_TIME_REPORT_EDIT')
                            : t('SHIFT_TIME_REPORT_BUTTON')}
                        </Button>
                      </div>
                    </div>
                  );
                })}
              </div>
            );
          })
        )}
      </Spin>
      <h4 className="mt-4">{t('SHIFT_PLANNING_OPEN_SHIFTS')}</h4>
      <OpenShiftsWeek weekStart={weekStart} />
      {reportShift && me && (
        <ReportTimeModal
          shift={reportShift}
          username={me.username}
          open
          onClose={() => setReportShift(null)}
        />
      )}
    </div>
  );
}
