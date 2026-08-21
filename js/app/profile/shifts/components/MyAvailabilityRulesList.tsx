import React from 'react';
import { App, Button, List, Popconfirm, Tag } from 'antd';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import {
  useDeleteAvailabilityRuleMutation,
  useGetMyAvailabilityRulesQuery,
} from '../../../api/slice';
import { AvailabilityRuleType } from '../../../api/types';

dayjs.extend(isoWeek);

const TYPE_COLORS: Record<AvailabilityRuleType, string> = {
  available: 'green',
  unavailable: 'red',
};

export default function MyAvailabilityRulesList() {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const { data, isFetching } = useGetMyAvailabilityRulesQuery();
  const [deleteRule, { isLoading: isDeleting }] =
    useDeleteAvailabilityRuleMutation();

  const rules = data?.['hydra:member'] ?? [];

  return (
    <List
      loading={isFetching}
      dataSource={rules}
      locale={{ emptyText: t('SHIFT_PLANNING_AVAILABILITY_EMPTY') }}
      renderItem={rule => (
        <List.Item
          actions={[
            <Popconfirm
              key="delete"
              title={t('SHIFT_PLANNING_DELETE_CONFIRM')}
              onConfirm={async () => {
                try {
                  await deleteRule(rule['@id']).unwrap();
                  message.success(t('SHIFT_PLANNING_DELETED'));
                } catch (e) {
                  message.error(t('SHIFT_PLANNING_ERROR'));
                }
              }}>
              <Button size="small" danger loading={isDeleting}>
                {t('SHIFT_PLANNING_DELETE')}
              </Button>
            </Popconfirm>,
          ]}>
          <List.Item.Meta
            title={
              <>
                {dayjs().isoWeekday(rule.dayOfWeek).format('dddd')}{' '}
                {rule.startTime}
                {' - '}
                {rule.endTime}{' '}
                <Tag color={TYPE_COLORS[rule.type]}>
                  {t(
                    `SHIFT_PLANNING_AVAILABILITY_TYPE_${rule.type.toUpperCase()}`,
                  )}
                </Tag>
              </>
            }
            description={rule.comment}
          />
        </List.Item>
      )}
    />
  );
}
