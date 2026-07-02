import React from 'react';
import { App, Button, Popconfirm } from 'antd';
import { CopyOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useCopyWeekMutation } from '../../../api/slice';

type Props = {
  weekStart: Dayjs;
};

export default function CopyWeekButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [copyWeek, { isLoading }] = useCopyWeekMutation();

  const sourceWeek = weekStart.subtract(7, 'day');

  const onConfirm = async () => {
    try {
      await copyWeek({
        sourceWeek: sourceWeek.format('YYYY-MM-DD'),
        targetWeek: weekStart.format('YYYY-MM-DD'),
      }).unwrap();
      message.success(t('SHIFT_PLANNING_COPY_SUCCESS'));
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Popconfirm
      title={t('SHIFT_PLANNING_COPY_CONFIRM', {
        source: sourceWeek.format('LL'),
        target: weekStart.format('LL'),
      })}
      onConfirm={onConfirm}>
      <Button icon={<CopyOutlined />} loading={isLoading}>
        {t('SHIFT_PLANNING_COPY_LAST_WEEK')}
      </Button>
    </Popconfirm>
  );
}
