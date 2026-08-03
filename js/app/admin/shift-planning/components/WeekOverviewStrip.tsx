import React from 'react';
import { Progress, Tooltip } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetShiftDashboardQuery } from '../../../api/slice';

type Props = {
  weekStart: Dayjs;
  onSelectWeek: (weekStart: Dayjs) => void;
};

/**
 * Always-visible row of circular fill-rate indicators for the upcoming
 * weeks, so staffing completeness is glanceable without leaving the
 * planning grid (replaces the old standalone "Dashboard" page/view).
 * Clicking a circle jumps the grid to that week.
 */
export default function WeekOverviewStrip({ weekStart, onSelectWeek }: Props) {
  const { t } = useTranslation();
  const { data, isFetching } = useGetShiftDashboardQuery({ weeks: 5 });

  const weeks = data?.weeks ?? [];
  const selectedKey = weekStart.format('YYYY-MM-DD');

  return (
    <div className="shift-planning__week-strip">
      {weeks.map(week => {
        const isSelected = week.weekStart === selectedKey;

        return (
          <Tooltip
            key={week.weekStart}
            title={t('SHIFT_PLANNING_WEEK_STRIP_TOOLTIP', {
              week: dayjs(week.weekStart).isoWeek(),
              start: dayjs(week.weekStart).format('DD MMM'),
              end: dayjs(week.weekEnd).format('DD MMM'),
              assigned: week.totalAssignments,
              slots: week.totalSlots,
              status: week.published
                ? t('SHIFT_PLANNING_PUBLISHED')
                : t('SHIFT_PLANNING_DASHBOARD_STATUS_DRAFT'),
            })}>
            <button
              type="button"
              className={
                'shift-planning__week-strip-item' +
                (isSelected ? ' shift-planning__week-strip-item--active' : '')
              }
              onClick={() => onSelectWeek(dayjs(week.weekStart))}>
              <Progress
                type="circle"
                size={44}
                percent={Math.round(week.fillRate * 100)}
                strokeColor={week.published ? '#52c41a' : '#1677ff'}
                trailColor="#f0f0f0"
                format={() => dayjs(week.weekStart).isoWeek()}
              />
            </button>
          </Tooltip>
        );
      })}
      {!isFetching && weeks.length === 0 && (
        <span className="text-muted">
          {t('SHIFT_PLANNING_WEEK_STRIP_EMPTY')}
        </span>
      )}
    </div>
  );
}
