import React, { useEffect } from 'react';
import { App, Button, Form, Input, Modal, Select, TimePicker } from 'antd';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import {
  usePostAvailabilityRuleMutation,
  usePutAvailabilityRuleMutation,
} from '../../../api/slice';
import { AvailabilityRule, AvailabilityRuleType, Uri } from '../../../api/types';

dayjs.extend(isoWeek);

// ISO day of week, 1 (Monday) to 7 (Sunday)
const DAYS_OF_WEEK = [1, 2, 3, 4, 5, 6, 7];

export type AvailabilityRuleModalState = {
  rule?: AvailabilityRule;
  /** The employee this new rule is for — only used when creating */
  user?: Uri;
} | null;

type Props = {
  state: AvailabilityRuleModalState;
  onClose: () => void;
};

type FormValues = {
  type: AvailabilityRuleType;
  dayOfWeek: number;
  times: [Dayjs, Dayjs];
  comment?: string;
};

export default function AvailabilityRuleModal({ state, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postAvailabilityRule, { isLoading: isCreating }] =
    usePostAvailabilityRuleMutation();
  const [putAvailabilityRule, { isLoading: isUpdating }] =
    usePutAvailabilityRuleMutation();

  const rule = state?.rule;

  useEffect(() => {
    if (!state) {
      return;
    }
    if (state.rule) {
      form.setFieldsValue({
        type: state.rule.type,
        dayOfWeek: state.rule.dayOfWeek,
        times: [
          dayjs(state.rule.startTime, 'HH:mm'),
          dayjs(state.rule.endTime, 'HH:mm'),
        ],
        comment: state.rule.comment ?? undefined,
      });
    } else {
      form.setFieldsValue({
        type: 'available',
        dayOfWeek: 1,
        times: undefined,
        comment: undefined,
      });
    }
  }, [state, form]);

  const onFinish = async (values: FormValues) => {
    try {
      const payload = {
        type: values.type,
        dayOfWeek: values.dayOfWeek,
        startTime: values.times[0].format('HH:mm'),
        endTime: values.times[1].format('HH:mm'),
        comment: values.comment,
      };
      if (rule) {
        await putAvailabilityRule({ '@id': rule['@id'], ...payload }).unwrap();
      } else {
        await postAvailabilityRule({ ...payload, user: state?.user }).unwrap();
      }
      message.success(t('SHIFT_PLANNING_SAVED'));
      onClose();
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={state !== null}
      title={
        rule
          ? t('SHIFT_PLANNING_AVAILABILITY_EDIT')
          : t('SHIFT_PLANNING_AVAILABILITY_ADD')
      }
      onCancel={onClose}
      destroyOnHidden
      footer={[
        <Button
          key="submit"
          type="primary"
          loading={isCreating || isUpdating}
          onClick={() => form.submit()}>
          {t('SHIFT_PLANNING_SAVE')}
        </Button>,
      ]}>
      <Form form={form} layout="vertical" onFinish={onFinish}>
        <Form.Item
          name="type"
          label={t('SHIFT_PLANNING_AVAILABILITY_TYPE')}
          rules={[{ required: true }]}>
          <Select
            options={[
              {
                value: 'available',
                label: t('SHIFT_PLANNING_AVAILABILITY_TYPE_AVAILABLE'),
              },
              {
                value: 'unavailable',
                label: t('SHIFT_PLANNING_AVAILABILITY_TYPE_UNAVAILABLE'),
              },
            ]}
          />
        </Form.Item>
        <Form.Item
          name="dayOfWeek"
          label={t('SHIFT_PLANNING_AVAILABILITY_DAY')}
          rules={[{ required: true }]}>
          <Select
            options={DAYS_OF_WEEK.map(day => ({
              value: day,
              label: dayjs().isoWeekday(day).format('dddd'),
            }))}
          />
        </Form.Item>
        <Form.Item
          name="times"
          label={t('SHIFT_PLANNING_TIME')}
          rules={[{ required: true }]}>
          <TimePicker.RangePicker
            style={{ width: '100%' }}
            format="HH:mm"
            minuteStep={5}
            allowClear={false}
          />
        </Form.Item>
        <Form.Item name="comment" label={t('SHIFT_PLANNING_COMMENT')}>
          <Input.TextArea rows={2} maxLength={65535} />
        </Form.Item>
      </Form>
    </Modal>
  );
}
