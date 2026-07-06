import React from 'react';
import { App, Button, Popconfirm } from 'antd';
import { ScheduleOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useSyncDispatchMutation } from '../../../api/slice';

type Props = {
  weekStart: Dayjs;
};

/**
 * Manually adds every courier assigned to a shift this week to the dispatch
 * (an empty TaskList per assigned day). Shifts no longer do this
 * automatically on create/update/copy — it's an explicit dispatcher action.
 */
export default function AddToDispatchButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [syncDispatch, { isLoading }] = useSyncDispatchMutation();

  const onConfirm = async () => {
    try {
      const { added } = await syncDispatch({
        week: weekStart.format('YYYY-MM-DD'),
      }).unwrap();
      message.success(t('SHIFT_PLANNING_DISPATCH_SYNCED', { count: added }));
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Popconfirm
      title={t('SHIFT_PLANNING_ADD_TO_DISPATCH_CONFIRM')}
      overlayStyle={{ maxWidth: 420 }}
      onConfirm={onConfirm}>
      <Button icon={<ScheduleOutlined />} loading={isLoading}>
        {t('SHIFT_PLANNING_ADD_TO_DISPATCH')}
      </Button>
    </Popconfirm>
  );
}
