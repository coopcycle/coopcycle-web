import React, { useState } from 'react';
import { Alert, App, Button, Empty, Modal, Spin, Tag } from 'antd';
import { ThunderboltOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import moment from 'moment';
import { useTranslation } from 'react-i18next';
import {
  useBatchCreateShiftsMutation,
  useGenerateScheduleMutation,
} from '../../../api/slice';
import { ShiftScheduleSuggestion } from '../../../api/types';
import DemandCoverageChart from './DemandCoverageChart';

type Props = {
  weekStart: Dayjs;
};

export default function GenerateScheduleButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [open, setOpen] = useState(false);
  const [suggestion, setSuggestion] = useState<ShiftScheduleSuggestion | null>(
    null,
  );

  const [generateSchedule, { isLoading: isGenerating }] =
    useGenerateScheduleMutation();
  const [batchCreateShifts, { isLoading: isApplying }] =
    useBatchCreateShiftsMutation();

  const onOpen = async () => {
    setOpen(true);
    setSuggestion(null);
    try {
      const result = await generateSchedule({
        week: weekStart.format('YYYY-MM-DD'),
      }).unwrap();
      setSuggestion(result);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
      setOpen(false);
    }
  };

  const onApply = async () => {
    if (!suggestion) {
      return;
    }
    try {
      const { created } = await batchCreateShifts({
        shifts: suggestion.shifts,
      }).unwrap();
      message.success(t('SHIFT_PLANNING_SCHEDULE_APPLIED', { count: created }));
      setOpen(false);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const totalShifts = suggestion?.shifts.length ?? 0;
  const observations = suggestion?.meta.observations ?? 0;
  const thinHistory = observations < 20;

  return (
    <>
      <Button icon={<ThunderboltOutlined />} onClick={onOpen}>
        {t('SHIFT_PLANNING_GENERATE')}
      </Button>
      <Modal
        open={open}
        width={860}
        title={t('SHIFT_PLANNING_GENERATE_TITLE', {
          // moment is locale-configured; dayjs lacks the localizedFormat plugin
          week: moment(weekStart.format('YYYY-MM-DD')).format('LL'),
        })}
        onCancel={() => setOpen(false)}
        destroyOnHidden
        footer={[
          <Button key="discard" onClick={() => setOpen(false)}>
            {t('SHIFT_PLANNING_DISCARD')}
          </Button>,
          <Button
            key="apply"
            type="primary"
            loading={isApplying}
            disabled={isGenerating || totalShifts === 0}
            onClick={onApply}>
            {t('SHIFT_PLANNING_APPLY_SHIFTS', { count: totalShifts })}
          </Button>,
        ]}>
        <Spin spinning={isGenerating}>
          {suggestion && totalShifts === 0 && !isGenerating && (
            <Empty description={t('SHIFT_PLANNING_NOT_ENOUGH_HISTORY')} />
          )}

          {suggestion && totalShifts > 0 && (
            <div className="schedule-suggestion">
              <p className="text-muted">
                {t('SHIFT_PLANNING_GENERATE_EXPLAINER', {
                  observations,
                  weeks: suggestion.meta.lookbackWeeks,
                  percentile: Math.round(suggestion.meta.serviceLevel * 100),
                })}{' '}
                {suggestion.meta.forecaster === 'prophet' ? (
                  <Tag color="purple">
                    {t('SHIFT_PLANNING_FORECASTER_PROPHET')}
                  </Tag>
                ) : (
                  <Tag>{t('SHIFT_PLANNING_FORECASTER_HEURISTIC')}</Tag>
                )}
              </p>

              {thinHistory && (
                <Alert
                  type="warning"
                  showIcon
                  className="mb-2"
                  message={t('SHIFT_PLANNING_THIN_HISTORY')}
                />
              )}

              <div className="schedule-suggestion__legend">
                <span>
                  <i className="schedule-suggestion__swatch schedule-suggestion__swatch--demand" />
                  {t('SHIFT_PLANNING_DEMAND')}
                </span>
                <span>
                  <i className="schedule-suggestion__swatch schedule-suggestion__swatch--coverage" />
                  {t('SHIFT_PLANNING_COVERAGE')}
                </span>
              </div>

              <div className="schedule-suggestion__grid">
                {suggestion.days.map(day => {
                  const dayShiftCount = suggestion.shifts.filter(s =>
                    s.startsAt.startsWith(day.date),
                  ).length;

                  return (
                    <div key={day.date} className="schedule-suggestion__day">
                      <div className="schedule-suggestion__day-header">
                        <strong>{dayjs(day.date).format('ddd D MMM')}</strong>
                        <Tag>{dayShiftCount}</Tag>
                      </div>
                      <div className="schedule-suggestion__chart">
                        <DemandCoverageChart buckets={day.buckets} />
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          )}
        </Spin>
      </Modal>
    </>
  );
}
