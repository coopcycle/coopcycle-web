import React from 'react';
import { App, Button, Drawer, Popconfirm, Space, Table, Tag } from 'antd';
import { useTranslation } from 'react-i18next';
import moment from 'moment';
import {
  useApproveHolidayRequestMutation,
  useRejectHolidayRequestMutation,
} from '../../../api/slice';
import { HolidayRequest, HolidayRequestStatus } from '../../../api/types';

const STATUS_COLORS: Record<HolidayRequestStatus, string> = {
  pending: 'gold',
  approved: 'green',
  rejected: 'red',
};

type Props = {
  open: boolean;
  holidayRequests: HolidayRequest[];
  onClose: () => void;
};

export default function HolidayRequestsDrawer({
  open,
  holidayRequests,
  onClose,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [approve, { isLoading: isApproving }] =
    useApproveHolidayRequestMutation();
  const [reject, { isLoading: isRejecting }] =
    useRejectHolidayRequestMutation();

  const action = async (fn: typeof approve, request: HolidayRequest) => {
    try {
      await fn(request['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_SAVED'));
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const columns = [
    {
      title: t('SHIFT_PLANNING_USER'),
      render: (r: HolidayRequest) => r.user.username,
    },
    {
      title: t('SHIFT_PLANNING_PERIOD'),
      render: (r: HolidayRequest) =>
        `${moment(r.startDate.slice(0, 10)).format('ll')} - ${moment(
          r.endDate.slice(0, 10),
        ).format('ll')}`,
    },
    {
      title: t('SHIFT_PLANNING_COMMENT'),
      render: (r: HolidayRequest) => r.comment,
    },
    {
      title: t('SHIFT_PLANNING_STATUS'),
      render: (r: HolidayRequest) => (
        <Tag color={STATUS_COLORS[r.status]}>
          {t(`SHIFT_PLANNING_STATUS_${r.status.toUpperCase()}`)}
        </Tag>
      ),
    },
    {
      title: '',
      render: (r: HolidayRequest) =>
        r.status === 'pending' ? (
          <Space>
            <Popconfirm
              title={t('SHIFT_PLANNING_APPROVE_CONFIRM')}
              onConfirm={() => action(approve, r)}>
              <Button size="small" type="primary" loading={isApproving}>
                {t('SHIFT_PLANNING_APPROVE')}
              </Button>
            </Popconfirm>
            <Popconfirm
              title={t('SHIFT_PLANNING_REJECT_CONFIRM')}
              onConfirm={() => action(reject, r)}>
              <Button size="small" danger loading={isRejecting}>
                {t('SHIFT_PLANNING_REJECT')}
              </Button>
            </Popconfirm>
          </Space>
        ) : null,
    },
  ];

  return (
    <Drawer
      open={open}
      onClose={onClose}
      width={720}
      title={t('SHIFT_PLANNING_HOLIDAY_REQUESTS')}>
      <Table
        rowKey="@id"
        dataSource={holidayRequests}
        columns={columns}
        pagination={false}
        size="small"
      />
    </Drawer>
  );
}
