import React from 'react';
import { App, Button, DatePicker, Form, Input } from 'antd';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { usePostHolidayRequestMutation } from '../../../api/slice';

type FormValues = {
  range: [Dayjs, Dayjs];
  comment?: string;
};

export default function HolidayRequestForm() {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postHolidayRequest, { isLoading }] = usePostHolidayRequestMutation();

  const onFinish = async (values: FormValues) => {
    try {
      await postHolidayRequest({
        startDate: values.range[0].format('YYYY-MM-DD'),
        endDate: values.range[1].format('YYYY-MM-DD'),
        comment: values.comment,
      }).unwrap();
      message.success(t('SHIFT_PLANNING_REQUEST_SENT'));
      form.resetFields();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Form form={form} layout="vertical" onFinish={onFinish}>
      <Form.Item
        name="range"
        label={t('SHIFT_PLANNING_PERIOD')}
        rules={[{ required: true }]}>
        <DatePicker.RangePicker style={{ width: '100%' }} />
      </Form.Item>
      <Form.Item name="comment" label={t('SHIFT_PLANNING_COMMENT')}>
        <Input.TextArea rows={2} />
      </Form.Item>
      <Button type="primary" htmlType="submit" loading={isLoading}>
        {t('SHIFT_PLANNING_REQUEST_HOLIDAY')}
      </Button>
    </Form>
  );
}
