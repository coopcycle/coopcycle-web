import React from 'react';
import { App, Button, List, Popconfirm, Tag } from 'antd';
import { useTranslation } from 'react-i18next';
import moment from 'moment';
import {
  useDeleteHolidayRequestMutation,
  useGetMyHolidayRequestsQuery,
} from '../../../api/slice';
import { HolidayRequestStatus } from '../../../api/types';

const STATUS_COLORS: Record<HolidayRequestStatus, string> = {
  pending: 'gold',
  approved: 'green',
  rejected: 'red',
};

export default function MyHolidayRequestsList() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const { data, isFetching } = useGetMyHolidayRequestsQuery();
  const [deleteRequest, { isLoading: isDeleting }] =
    useDeleteHolidayRequestMutation();

  const requests = data?.['hydra:member'] ?? [];

  return (
    <List
      loading={isFetching}
      dataSource={requests}
      locale={{ emptyText: t('SHIFT_PLANNING_NO_REQUESTS') }}
      renderItem={request => (
        <List.Item
          actions={
            request.status === 'pending'
              ? [
                  <Popconfirm
                    key="delete"
                    title={t('SHIFT_PLANNING_DELETE_CONFIRM')}
                    onConfirm={async () => {
                      try {
                        await deleteRequest(request['@id']).unwrap();
                        message.success(t('SHIFT_PLANNING_DELETED'));
                      } catch (e) {
                        message.error(t('SHIFT_PLANNING_ERROR'));
                      }
                    }}>
                    <Button size="small" danger loading={isDeleting}>
                      {t('SHIFT_PLANNING_DELETE')}
                    </Button>
                  </Popconfirm>,
                ]
              : []
          }>
          <List.Item.Meta
            title={
              <>
                {moment(request.startDate.slice(0, 10)).format('ll')}
                {' - '}
                {moment(request.endDate.slice(0, 10)).format('ll')}{' '}
                <Tag color={STATUS_COLORS[request.status]}>
                  {t(`SHIFT_PLANNING_STATUS_${request.status.toUpperCase()}`)}
                </Tag>
              </>
            }
            description={request.comment}
          />
        </List.Item>
      )}
    />
  );
}
