import React, { useEffect } from 'react';
import { App, Button, ColorPicker, Form, Input, Modal, Space, Tooltip } from 'antd';
import { UndoOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  usePostShiftActivityMutation,
  usePutShiftActivityMutation,
} from '../../../api/slice';
import { ShiftActivity } from '../../../api/types';
import { shiftTypeColor } from '../utils/shiftTypeColor';

export type ActivityModalState = { activity?: ShiftActivity } | null;

type Props = {
  state: ActivityModalState;
  onClose: () => void;
};

type FormValues = {
  label: string;
  color: string;
};

export default function ActivityModal({ state, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postShiftActivity, { isLoading: isCreating }] =
    usePostShiftActivityMutation();
  const [putShiftActivity, { isLoading: isUpdating }] =
    usePutShiftActivityMutation();

  const activity = state?.activity;
  const color = Form.useWatch('color', form);

  useEffect(() => {
    if (!state) {
      return;
    }
    form.setFieldsValue({
      label: state.activity?.label ?? '',
      color:
        state.activity?.color ||
        shiftTypeColor(state.activity?.slug ?? ''),
    });
  }, [state, form]);

  const onFinish = async (values: FormValues) => {
    try {
      if (activity) {
        await putShiftActivity({
          '@id': activity['@id'],
          label: values.label,
          color: values.color,
        }).unwrap();
      } else {
        await postShiftActivity({
          label: values.label,
          color: values.color,
        }).unwrap();
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
      title={activity ? t('SHIFT_ACTIVITY_EDIT') : t('SHIFT_ACTIVITY_NEW')}
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
          name="label"
          label={t('SHIFT_ACTIVITY_LABEL')}
          rules={[{ required: true }]}>
          <Input maxLength={255} />
        </Form.Item>
        <Form.Item name="color" label={t('SHIFT_ACTIVITY_COLOR')}>
          <Space>
            <ColorPicker
              disabledAlpha
              showText
              value={color}
              onChangeComplete={c =>
                form.setFieldValue('color', c.toHexString())
              }
            />
            {activity && (
              <Tooltip title={t('SHIFT_PLANNING_RESET_COLOR')}>
                <Button
                  type="text"
                  size="small"
                  icon={<UndoOutlined />}
                  disabled={color === shiftTypeColor(activity.slug)}
                  onClick={() =>
                    form.setFieldValue('color', shiftTypeColor(activity.slug))
                  }
                />
              </Tooltip>
            )}
          </Space>
        </Form.Item>
      </Form>
    </Modal>
  );
}
