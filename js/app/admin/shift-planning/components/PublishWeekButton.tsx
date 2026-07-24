import React from 'react';
import { App, Button, Popconfirm, Tag } from 'antd';
import { CheckCircleOutlined, SendOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import moment from 'moment';
import { useTranslation } from 'react-i18next';
import {
  useGetSchedulePublicationsQuery,
  usePublishWeekMutation,
} from '../../../api/slice';

// moment (locale-aware, see utils/antd.js) is used for display,
// parsing the plain date to stay independent of the browser timezone
const formatDay = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
};

/**
 * Publishes the schedule of the visible week: couriers get notified by email
 * and can start applying to open shifts. Publishing is one-way (no unpublish).
 */
export default function PublishWeekButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const week = weekStart.format('YYYY-MM-DD');

  const { data: publications } = useGetSchedulePublicationsQuery({
    weekStart: week,
  });
  const [publishWeek, { isLoading }] = usePublishWeekMutation();

  const isPublished = (publications ?? []).length > 0;

  if (isPublished) {
    return (
      <Tag icon={<CheckCircleOutlined />} color="success" className="me-0">
        {t('SHIFT_PLANNING_PUBLISHED')}
      </Tag>
    );
  }

  const onConfirm = async () => {
    try {
      await publishWeek({ week }).unwrap();
      message.success(t('SHIFT_PLANNING_PUBLISH_SUCCESS'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Popconfirm
      title={t('SHIFT_PLANNING_PUBLISH_CONFIRM', { week: formatDay(weekStart) })}
      description={t('SHIFT_PLANNING_PUBLISH_CONFIRM_NOTE')}
      overlayStyle={{ maxWidth: 420 }}
      onConfirm={onConfirm}>
      <Button icon={<SendOutlined />} loading={isLoading}>
        {t('SHIFT_PLANNING_PUBLISH')}
      </Button>
    </Popconfirm>
  );
}
