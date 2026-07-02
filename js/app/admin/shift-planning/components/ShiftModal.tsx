import React, { useEffect } from 'react';
import {
  Alert,
  App,
  Button,
  DatePicker,
  Form,
  InputNumber,
  Modal,
  Popconfirm,
  Select,
  TimePicker,
} from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import {
  useDeleteShiftMutation,
  usePostShiftMutation,
  usePutShiftMutation,
} from '../../../api/slice';
import { HolidayRequest, PlanningUser, Shift, Uri } from '../../../api/types';
import { holidayCoversDay } from '../utils/date';

export type ShiftModalState = {
  shift?: Shift;
  date?: Dayjs;
  userUri?: Uri;
} | null;

type Props = {
  state: ShiftModalState;
  shiftTypes: string[];
  users: PlanningUser[];
  holidayRequests: HolidayRequest[];
  onClose: () => void;
};

type FormValues = {
  type: string;
  date: Dayjs;
  times: [Dayjs, Dayjs];
  slots: number;
  users: Uri[];
};

export default function ShiftModal({
  state,
  shiftTypes,
  users,
  holidayRequests,
  onClose,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postShift, { isLoading: isCreating }] = usePostShiftMutation();
  const [putShift, { isLoading: isUpdating }] = usePutShiftMutation();
  const [deleteShift, { isLoading: isDeleting }] = useDeleteShiftMutation();

  const shift = state?.shift;

  useEffect(() => {
    if (!state) {
      return;
    }
    if (state.shift) {
      form.setFieldsValue({
        type: state.shift.type,
        date: dayjs(state.shift.startsAt),
        times: [dayjs(state.shift.startsAt), dayjs(state.shift.endsAt)],
        slots: state.shift.slots,
        users: state.shift.assignments.map(a => a.user['@id']),
      });
    } else {
      form.setFieldsValue({
        type: shiftTypes[0],
        date: state.date || dayjs(),
        times: [
          (state.date || dayjs()).hour(9).minute(0),
          (state.date || dayjs()).hour(17).minute(0),
        ],
        slots: 1,
        users: state.userUri ? [state.userUri] : [],
      });
    }
  }, [state, form, shiftTypes]);

  const selectedDate = Form.useWatch('date', form);
  const selectedUsers = Form.useWatch('users', form) || [];

  const conflicts = selectedDate
    ? selectedUsers.filter(uri =>
        holidayRequests.some(
          h =>
            h.status === 'approved' &&
            h.user['@id'] === uri &&
            holidayCoversDay(h, selectedDate),
        ),
      )
    : [];

  const conflictNames = conflicts
    .map(uri => users.find(u => u['@id'] === uri)?.username || uri)
    .join(', ');

  const onFinish = async (values: FormValues) => {
    const date = values.date.format('YYYY-MM-DD');
    const payload = {
      type: values.type,
      startsAt: `${date}T${values.times[0].format('HH:mm:ss')}`,
      endsAt: `${date}T${values.times[1].format('HH:mm:ss')}`,
      slots: values.slots,
      users: values.users,
    };

    try {
      if (shift) {
        await putShift({ '@id': shift['@id'], ...payload }).unwrap();
      } else {
        await postShift(payload).unwrap();
      }
      message.success(t('SHIFT_PLANNING_SAVED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const onDelete = async () => {
    if (!shift) {
      return;
    }
    try {
      await deleteShift(shift['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_DELETED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={state !== null}
      title={shift ? t('SHIFT_PLANNING_EDIT_SHIFT') : t('SHIFT_PLANNING_NEW_SHIFT')}
      onCancel={onClose}
      destroyOnClose
      footer={[
        shift ? (
          <Popconfirm
            key="delete"
            title={t('SHIFT_PLANNING_DELETE_CONFIRM')}
            onConfirm={onDelete}>
            <Button danger loading={isDeleting}>
              {t('SHIFT_PLANNING_DELETE')}
            </Button>
          </Popconfirm>
        ) : null,
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
          label={t('SHIFT_PLANNING_TYPE')}
          rules={[{ required: true }]}>
          <Select options={shiftTypes.map(type => ({ value: type, label: type }))} />
        </Form.Item>
        <Form.Item
          name="date"
          label={t('SHIFT_PLANNING_DATE')}
          rules={[{ required: true }]}>
          <DatePicker style={{ width: '100%' }} allowClear={false} />
        </Form.Item>
        <Form.Item
          name="times"
          label={t('SHIFT_PLANNING_TIME')}
          rules={[{ required: true }]}>
          <TimePicker.RangePicker
            style={{ width: '100%' }}
            format="HH:mm"
            minuteStep={15}
            allowClear={false}
          />
        </Form.Item>
        <Form.Item
          name="slots"
          label={t('SHIFT_PLANNING_SLOTS')}
          rules={[{ required: true }]}>
          <InputNumber min={1} style={{ width: '100%' }} />
        </Form.Item>
        <Form.Item name="users" label={t('SHIFT_PLANNING_ASSIGNEES')}>
          <Select
            mode="multiple"
            optionFilterProp="label"
            options={users.map(u => ({
              value: u['@id'],
              label: u.username,
            }))}
          />
        </Form.Item>
        {conflicts.length > 0 && (
          <Alert
            type="warning"
            showIcon
            message={t('SHIFT_PLANNING_HOLIDAY_CONFLICT', {
              names: conflictNames,
            })}
          />
        )}
      </Form>
    </Modal>
  );
}
