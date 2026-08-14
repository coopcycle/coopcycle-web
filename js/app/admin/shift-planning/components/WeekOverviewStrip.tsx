import React, { useEffect, useState } from 'react';
import { Button, Progress, Tooltip } from 'antd';
import { LeftOutlined, RightOutlined } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetShiftDashboardQuery } from '../../../api/slice';

// How many week circles are shown at once
const VISIBLE_WEEKS = 5;

type Props = {
  weekStart: Dayjs;
  onSelectWeek: (weekStart: Dayjs) => void;
};

/**
 * Always-visible row of circular fill-rate indicators, so staffing
 * completeness is glanceable without leaving the planning grid (replaces the
 * old standalone "Dashboard" page/view). Clicking a circle jumps the grid to
 * that week.
 *
 * The visible window of weeks is freely panned with the arrows — it isn't
 * tied to the current week — and re-centers on the selected week whenever it
 * changes from elsewhere (e.g. the week picker below) and falls outside the
 * currently visible range.
 */
export default function WeekOverviewStrip({ weekStart, onSelectWeek }: Props) {
  const { t } = useTranslation();

  const [windowStart, setWindowStart] = useState(() =>
    weekStart.subtract(2, 'week'),
  );

  useEffect(() => {
    const windowEnd = windowStart.add(VISIBLE_WEEKS - 1, 'week');
    if (weekStart.isBefore(windowStart) || weekStart.isAfter(windowEnd)) {
      setWindowStart(weekStart.subtract(2, 'week'));
    }
    // Only react to the selected week moving out of view, not to panning
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [weekStart.format('YYYY-MM-DD')]);

  const { data, isFetching } = useGetShiftDashboardQuery({
    weeks: VISIBLE_WEEKS,
    from: windowStart.format('YYYY-MM-DD'),
  });

  const weeks = data?.weeks ?? [];
  const selectedKey = weekStart.format('YYYY-MM-DD');

  return (
    <div className="shift-planning__week-strip">
      <Button
        type="text"
        shape="circle"
        icon={<LeftOutlined />}
        aria-label={t('SHIFT_PLANNING_WEEK_STRIP_PREV')}
        onClick={() => setWindowStart(w => w.subtract(1, 'week'))}
      />
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
      <Button
        type="text"
        shape="circle"
        icon={<RightOutlined />}
        aria-label={t('SHIFT_PLANNING_WEEK_STRIP_NEXT')}
        onClick={() => setWindowStart(w => w.add(1, 'week'))}
      />
    </div>
  );
}
