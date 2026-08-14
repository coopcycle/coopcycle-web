import React, { useState } from 'react';
import { App, Button, Popconfirm, Space, Table } from 'antd';
import {
  DeleteOutlined,
  EditOutlined,
  PlusOutlined,
} from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  useDeleteShiftActivityMutation,
  useGetShiftActivitiesQuery,
} from '../../../api/slice';
import { ShiftActivity } from '../../../api/types';
import { activityDisplayLabel } from '../utils/activityLabel';
import { shiftTypeColor } from '../utils/shiftTypeColor';
import ActivityModal, { ActivityModalState } from './ActivityModal';

export default function ActivitiesManager() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const { data: activities, isFetching } = useGetShiftActivitiesQuery();
  const [deleteShiftActivity] = useDeleteShiftActivityMutation();

  const [modalState, setModalState] = useState<ActivityModalState>(null);

  const onDelete = async (activity: ShiftActivity) => {
    try {
      await deleteShiftActivity(activity['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_DELETED'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const columns = [
    {
      title: t('SHIFT_ACTIVITY_LABEL'),
      key: 'label',
      render: (_: unknown, activity: ShiftActivity) => (
        <span>
          <span
            className="shift-type-dot"
            style={{
              backgroundColor: activity.color || shiftTypeColor(activity.slug),
            }}
          />
          {activityDisplayLabel(activity, t)}
        </span>
      ),
    },
    {
      title: '',
      key: 'actions',
      width: 100,
      render: (_: unknown, activity: ShiftActivity) => (
        <Space>
          <Button
            type="text"
            size="small"
            icon={<EditOutlined />}
            onClick={() => setModalState({ activity })}
          />
          <Popconfirm
            title={t('SHIFT_ACTIVITY_DELETE_CONFIRM')}
            onConfirm={() => onDelete(activity)}>
            <Button type="text" size="small" danger icon={<DeleteOutlined />} />
          </Popconfirm>
        </Space>
      ),
    },
  ];

  return (
    <div className="shift-planning__activities">
      <div className="mb-3">
        <Button
          type="primary"
          icon={<PlusOutlined />}
          onClick={() => setModalState({})}>
          {t('SHIFT_ACTIVITY_NEW')}
        </Button>
      </div>
      <Table
        rowKey="@id"
        size="small"
        loading={isFetching}
        dataSource={activities ?? []}
        columns={columns}
        pagination={false}
      />
      <ActivityModal state={modalState} onClose={() => setModalState(null)} />
    </div>
  );
}
