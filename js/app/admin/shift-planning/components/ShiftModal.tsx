import React, { useEffect, useState } from 'react';
import {
  Alert,
  App,
  Button,
  DatePicker,
  Form,
  Input,
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
  useGetSkillsQuery,
  usePostShiftMutation,
  usePutShiftMutation,
} from '../../../api/slice';
import { HolidayRequest, PlanningUser, Shift, Uri } from '../../../api/types';
import {
  holidayCoversDay,
  rangesOverlap,
  wallClock,
  wallClockTime,
} from '../utils/date';
import { shiftTypeColor } from '../utils/shiftTypeColor';
import { datePickerProps } from '../../../utils/antd';
import ReportTimeModal from './ReportTimeModal';

export type ShiftModalState = {
  shift?: Shift;
  date?: Dayjs;
  userUri?: Uri;
  /** Prefill the type Select, e.g. when created from a Type-view row */
  type?: string;
  /** Prefill the start time, e.g. when created from a Calendar-view click; end defaults to start + 2h */
  time?: Dayjs;
} | null;

type Props = {
  state: ShiftModalState;
  shiftTypes: string[];
  users: PlanningUser[];
  holidayRequests: HolidayRequest[];
  shifts: Shift[];
  typeColors?: Record<string, string>;
  onClose: () => void;
};

type FormValues = {
  type: string;
  date: Dayjs;
  times: [Dayjs, Dayjs];
  slots: number;
  breakMinutes: number;
  comment?: string;
  requiredSkills: Uri[];
  users: Uri[];
};

export default function ShiftModal({
  state,
  shiftTypes,
  users,
  holidayRequests,
  shifts,
  typeColors,
  onClose,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();
  const [form] = Form.useForm<FormValues>();

  const [postShift, { isLoading: isCreating }] = usePostShiftMutation();
  const [putShift, { isLoading: isUpdating }] = usePutShiftMutation();
  const [deleteShift, { isLoading: isDeleting }] = useDeleteShiftMutation();

  const { data: skills } = useGetSkillsQuery();

  const shift = state?.shift;

  // Worked-time reports are edited against the freshest version of the shift
  // (the modal keeps a snapshot in `state`, but `shifts` refetches on change)
  const freshShift =
    (shift && shifts.find(s => s['@id'] === shift['@id'])) || shift;
  const [reportUser, setReportUser] = useState<string | null>(null);

  useEffect(() => {
    if (!state) {
      return;
    }
    if (state.shift) {
      form.setFieldsValue({
        type: state.shift.type,
        date: dayjs(wallClock(state.shift.startsAt)),
        times: [
          dayjs(wallClock(state.shift.startsAt)),
          dayjs(wallClock(state.shift.endsAt)),
        ],
        slots: state.shift.slots,
        breakMinutes: state.shift.breakMinutes,
        comment: state.shift.comment ?? undefined,
        requiredSkills: state.shift.requiredSkills.map(s => s['@id']),
        users: state.shift.assignments.map(a => a.user['@id']),
      });
    } else {
      const start = state.time || (state.date || dayjs()).hour(9).minute(0);
      const end = state.time
        ? state.time.add(2, 'hour')
        : (state.date || dayjs()).hour(17).minute(0);

      form.setFieldsValue({
        type: state.type || shiftTypes[0],
        date: state.date || dayjs(),
        times: [start, end],
        slots: 1,
        breakMinutes: 0,
        comment: undefined,
        requiredSkills: [],
        users: state.userUri ? [state.userUri] : [],
      });
    }
  }, [state, form, shiftTypes]);

  const selectedDate = Form.useWatch('date', form);
  const selectedTimes = Form.useWatch('times', form);
  const selectedUsers = Form.useWatch('users', form) || [];
  const selectedSlots = Form.useWatch('slots', form);
  const selectedSkills = Form.useWatch('requiredSkills', form) || [];

  const isOverstaffed =
    typeof selectedSlots === 'number' && selectedUsers.length > selectedSlots;

  const usernameOf = (uri: Uri) =>
    users.find(u => u['@id'] === uri)?.username || uri;

  // Warn (never block) when an assigned user lacks a skill the shift requires.
  // A user's skills come from the planning-users query (user.skills).
  const skillGaps = selectedSkills.length
    ? selectedUsers
        .map(uri => {
          const user = users.find(u => u['@id'] === uri);
          const userSkillIds = new Set(
            (user?.skills ?? []).map(s => s['@id']),
          );
          const missing = selectedSkills.filter(
            skillUri => !userSkillIds.has(skillUri),
          );
          return { uri, missing };
        })
        .filter(g => g.missing.length > 0)
    : [];

  const skillNameOf = (uri: Uri) =>
    skills?.find(s => s['@id'] === uri)?.name || uri;

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

  const conflictNames = conflicts.map(usernameOf).join(', ');

  // Other shifts the selected users are already assigned to at the same time
  let shiftConflicts: { uri: Uri; shift: Shift }[] = [];
  if (selectedDate && selectedTimes?.[0] && selectedTimes?.[1]) {
    const date = selectedDate.format('YYYY-MM-DD');
    const start = `${date}T${selectedTimes[0].format('HH:mm:ss')}`;
    const end = `${date}T${selectedTimes[1].format('HH:mm:ss')}`;

    shiftConflicts = selectedUsers.flatMap(uri =>
      shifts
        .filter(
          s =>
            s['@id'] !== shift?.['@id'] &&
            s.assignments.some(a => a.user['@id'] === uri) &&
            rangesOverlap(
              start,
              end,
              wallClock(s.startsAt),
              wallClock(s.endsAt),
            ),
        )
        .map(s => ({ uri, shift: s })),
    );
  }

  const onFinish = async (values: FormValues) => {
    const date = values.date.format('YYYY-MM-DD');
    const payload = {
      type: values.type,
      startsAt: `${date}T${values.times[0].format('HH:mm:ss')}`,
      endsAt: `${date}T${values.times[1].format('HH:mm:ss')}`,
      slots: values.slots,
      breakMinutes: values.breakMinutes,
      comment: values.comment || null,
      requiredSkills: values.requiredSkills,
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
      destroyOnHidden
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
          <Select
            options={shiftTypes.map(type => ({
              value: type,
              label: (
                <span>
                  <span
                    className="shift-type-dot"
                    style={{
                      backgroundColor: shiftTypeColor(type, typeColors),
                    }}
                  />
                  {type}
                </span>
              ),
            }))}
          />
        </Form.Item>
        <Form.Item
          name="date"
          label={t('SHIFT_PLANNING_DATE')}
          rules={[{ required: true }]}>
          <DatePicker
            style={{ width: '100%' }}
            allowClear={false}
            format={datePickerProps.format}
          />
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
        <Form.Item
          name="breakMinutes"
          label={t('SHIFT_PLANNING_BREAK_MINUTES')}
          rules={[{ required: true }]}>
          <InputNumber min={0} step={5} style={{ width: '100%' }} />
        </Form.Item>
        <Form.Item
          name="requiredSkills"
          label={t('SHIFT_PLANNING_REQUIRED_SKILLS')}>
          <Select
            mode="multiple"
            optionFilterProp="label"
            placeholder={t('SHIFT_PLANNING_REQUIRED_SKILLS_PLACEHOLDER')}
            options={(skills ?? []).map(s => ({
              value: s['@id'],
              label: s.name,
            }))}
          />
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
        <Form.Item name="comment" label={t('SHIFT_PLANNING_COMMENT')}>
          <Input.TextArea rows={2} maxLength={65535} />
        </Form.Item>
        {freshShift && freshShift.assignments.length > 0 && (
          <Form.Item label={t('SHIFT_TIME_REPORT_SECTION')}>
            <div className="shift-worked-time">
              {freshShift.assignments.map(a => (
                <div key={a.user['@id']} className="shift-worked-time__row">
                  <span>
                    <strong>{a.user.username}</strong>{' '}
                    {a.adjustment ? (
                      <span className="shift-worked-time__reported">
                        {t('SHIFT_TIME_REPORT_SUMMARY', {
                          start: wallClockTime(a.adjustment.startsAt),
                          end: wallClockTime(a.adjustment.endsAt),
                          break: a.adjustment.breakMinutes,
                        })}
                      </span>
                    ) : (
                      <span className="text-muted">
                        {t('SHIFT_TIME_REPORT_AS_PLANNED')}
                      </span>
                    )}
                  </span>
                  <Button
                    type="link"
                    size="small"
                    onClick={() => setReportUser(a.user.username)}>
                    {a.adjustment
                      ? t('SHIFT_TIME_REPORT_EDIT')
                      : t('SHIFT_TIME_REPORT_BUTTON')}
                  </Button>
                </div>
              ))}
            </div>
          </Form.Item>
        )}
        {skillGaps.length > 0 && (
          <Alert
            type="warning"
            showIcon
            className="mb-2"
            message={
              <>
                {skillGaps.map(({ uri, missing }) => (
                  <div key={uri}>
                    {t('SHIFT_PLANNING_SKILL_GAP', {
                      name: usernameOf(uri),
                      skills: missing.map(skillNameOf).join(', '),
                    })}
                  </div>
                ))}
              </>
            }
          />
        )}
        {conflicts.length > 0 && (
          <Alert
            type="warning"
            showIcon
            className="mb-2"
            message={t('SHIFT_PLANNING_HOLIDAY_CONFLICT', {
              names: conflictNames,
            })}
          />
        )}
        {isOverstaffed && (
          <Alert
            type="warning"
            showIcon
            className="mb-2"
            message={t('SHIFT_PLANNING_OVERSTAFFED', {
              assigned: selectedUsers.length,
              slots: selectedSlots,
            })}
          />
        )}
        {shiftConflicts.length > 0 && (
          <Alert
            type="warning"
            showIcon
            message={
              <>
                {shiftConflicts.map(({ uri, shift: s }) => (
                  <div key={`${uri}|${s['@id']}`}>
                    {t('SHIFT_PLANNING_SHIFT_CONFLICT', {
                      name: usernameOf(uri),
                      type: s.type,
                      start: wallClockTime(s.startsAt),
                      end: wallClockTime(s.endsAt),
                    })}
                  </div>
                ))}
              </>
            }
          />
        )}
      </Form>
      {freshShift && reportUser && (
        <ReportTimeModal
          shift={freshShift}
          username={reportUser}
          userUri={
            freshShift.assignments.find(a => a.user.username === reportUser)
              ?.user['@id']
          }
          open
          onClose={() => setReportUser(null)}
        />
      )}
    </Modal>
  );
}
