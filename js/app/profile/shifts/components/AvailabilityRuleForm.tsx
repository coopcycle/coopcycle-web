import React from 'react';
import { App, Button, Form, Input, Select, TimePicker } from 'antd';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import { usePostAvailabilityRuleMutation } from '../../../api/slice';
import { AvailabilityRuleType } from '../../../api/types';

dayjs.extend(isoWeek);

// ISO day of week, 1 (Monday) to 7 (Sunday)
const DAYS_OF_WEEK = [1, 2, 3, 4, 5, 6, 7];

type FormValues = {
  type: AvailabilityRuleType;
  dayOfWeek: number;
  times: [Dayjs, Dayjs];
  comment?: string;
};

export default function AvailabilityRuleForm() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postAvailabilityRule, { isLoading }] =
    usePostAvailabilityRuleMutation();

  const onFinish = async (values: FormValues) => {
    try {
      await postAvailabilityRule({
        type: values.type,
        dayOfWeek: values.dayOfWeek,
        startTime: values.times[0].format('HH:mm'),
        endTime: values.times[1].format('HH:mm'),
        comment: values.comment,
      }).unwrap();
      message.success(t('SHIFT_PLANNING_AVAILABILITY_SAVED'));
      form.resetFields();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Form
      form={form}
      layout="vertical"
      onFinish={onFinish}
      initialValues={{ type: 'available' }}>
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
      <Button type="primary" htmlType="submit" loading={isLoading}>
        {t('SHIFT_PLANNING_AVAILABILITY_ADD')}
      </Button>
    </Form>
  );
}
