import React from 'react';
import { Card, Col, Row, Tag } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetShiftDashboardQuery } from '../../../api/slice';
import { ShiftDashboardWeekStatus } from '../../../api/types';

const STATUS_COLORS: Record<ShiftDashboardWeekStatus, string> = {
  draft: 'default',
  in_progress: 'processing',
  complete: 'success',
};

type Props = {
  onSelectWeek: (weekStart: Dayjs) => void;
};

export default function ShiftsDashboard({ onSelectWeek }: Props) {
  const { t } = useTranslation();
  const { data, isFetching } = useGetShiftDashboardQuery({ weeks: 5 });

  const weeks = data?.weeks ?? [];

  return (
    <Row gutter={[16, 16]}>
      {weeks.map(week => (
        <Col key={week.weekStart} xs={24} sm={12} md={8} lg={24 / 5}>
          <Card
            hoverable
            loading={isFetching}
            onClick={() => onSelectWeek(dayjs(week.weekStart))}
            title={t('SHIFT_PLANNING_DASHBOARD_WEEK_LABEL', {
              week: dayjs(week.weekStart).isoWeek(),
            })}
            extra={
              <Tag color={STATUS_COLORS[week.status]}>
                {t(`SHIFT_PLANNING_DASHBOARD_STATUS_${week.status.toUpperCase()}`)}
              </Tag>
            }>
            <p className="mb-1">
              {dayjs(week.weekStart).format('DD MMM')}
              {' → '}
              {dayjs(week.weekEnd).format('DD MMM')}
            </p>
            <p className="mb-0">
              <strong>
                {week.totalAssignments}/{week.totalSlots}
              </strong>
              {' · '}
              {Math.round(week.fillRate * 100)}%
            </p>
          </Card>
        </Col>
      ))}
    </Row>
  );
}
