import React from 'react';
import { App, Button, Popconfirm } from 'antd';
import { ClearOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import moment from 'moment';
import { useTranslation } from 'react-i18next';
import {
  useClearWeekMutation,
  useGetSchedulePublicationsQuery,
} from '../../../api/slice';

// moment (locale-aware, see utils/antd.js) is used for display,
// parsing the plain date to stay independent of the browser timezone
const formatDay = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
};

/**
 * Deletes every shift of the visible week in one go, so a dispatcher can
 * start a week's planning over. Disabled once the week is published —
 * couriers may already be relying on it (mirrors the backend's own check).
 */
export default function ClearWeekButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const week = weekStart.format('YYYY-MM-DD');

  const { data: publications } = useGetSchedulePublicationsQuery({
    weekStart: week,
  });
  const [clearWeek, { isLoading }] = useClearWeekMutation();

  const isPublished = (publications ?? []).length > 0;

  const onConfirm = async () => {
    try {
      const { cleared } = await clearWeek({ week }).unwrap();
      message.success(
        t('SHIFT_PLANNING_CLEAR_WEEK_SUCCESS', { count: cleared }),
      );
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Popconfirm
      title={t('SHIFT_PLANNING_CLEAR_WEEK_CONFIRM', {
        week: formatDay(weekStart),
      })}
      description={t('SHIFT_PLANNING_CLEAR_WEEK_CONFIRM_NOTE')}
      overlayStyle={{ maxWidth: 420 }}
      onConfirm={onConfirm}
      disabled={isPublished}>
      <Button
        danger
        icon={<ClearOutlined />}
        loading={isLoading}
        disabled={isPublished}
        title={isPublished ? t('SHIFT_PLANNING_CLEAR_WEEK_DISABLED') : undefined}>
        {t('SHIFT_PLANNING_CLEAR_WEEK')}
      </Button>
    </Popconfirm>
  );
}
