import React, { useEffect, useState } from 'react';
import { App, Button, Input, InputNumber, Modal, TimePicker } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useReportShiftTimeMutation } from '../../../api/slice';
import { Shift, Uri } from '../../../api/types';
import { wallClockTime } from '../utils/date';

type Props = {
  shift: Shift;
  /** Username of the assignee whose worked time is being reported */
  username: string;
  /** Set when a dispatcher reports for someone else; omit for self-reports */
  userUri?: Uri;
  open: boolean;
  onClose: () => void;
};

const toPicker = (time: string): Dayjs => dayjs(`2000-01-01T${time}:00`);

/**
 * Report the hours actually worked on a shift (overtime, left early, longer
 * break, …). The planned shift stays untouched; the report can be edited or
 * reverted at any time.
 */
export default function ReportTimeModal({
  shift,
  username,
  userUri,
  open,
  onClose,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const assignment = shift.assignments.find(a => a.user.username === username);
  const adjustment = assignment?.adjustment ?? null;

  const [start, setStart] = useState<Dayjs | null>(null);
  const [end, setEnd] = useState<Dayjs | null>(null);
  const [breakMinutes, setBreakMinutes] = useState(0);
  const [comment, setComment] = useState('');

  useEffect(() => {
    if (open) {
      // Prefill with the reported time if any, else the planned time
      setStart(toPicker(wallClockTime(adjustment?.startsAt ?? shift.startsAt)));
      setEnd(toPicker(wallClockTime(adjustment?.endsAt ?? shift.endsAt)));
      setBreakMinutes(adjustment?.breakMinutes ?? shift.breakMinutes);
      setComment(adjustment?.comment ?? '');
    }
  }, [open, shift, adjustment]);

  const [reportShiftTime, { isLoading }] = useReportShiftTimeMutation();

  const date = shift.startsAt.slice(0, 10);

  const onSave = async () => {
    if (!start || !end) {
      return;
    }
    try {
      await reportShiftTime({
        uri: shift['@id'],
        user: userUri,
        startsAt: `${date}T${start.format('HH:mm')}:00`,
        endsAt: `${date}T${end.format('HH:mm')}:00`,
        breakMinutes,
        comment: comment || null,
      }).unwrap();
      message.success(t('SHIFT_TIME_REPORT_SAVED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const onRevert = async () => {
    try {
      await reportShiftTime({ uri: shift['@id'], user: userUri, clear: true }).unwrap();
      message.success(t('SHIFT_TIME_REPORT_REVERTED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const invalid = !start || !end || !end.isAfter(start);

  return (
    <Modal
      open={open}
      title={t('SHIFT_TIME_REPORT_TITLE', {
        username,
        date: dayjs(date).format('ddd D MMM'),
      })}
      onCancel={onClose}
      destroyOnHidden
      footer={[
        adjustment && (
          <Button key="revert" danger onClick={onRevert} loading={isLoading}>
            {t('SHIFT_TIME_REPORT_REVERT')}
          </Button>
        ),
        <Button key="cancel" onClick={onClose}>
          {t('SHIFT_PLANNING_CANCEL')}
        </Button>,
        <Button
          key="save"
          type="primary"
          disabled={invalid}
          loading={isLoading}
          onClick={onSave}>
          {t('SHIFT_PLANNING_SAVE')}
        </Button>,
      ]}>
      <p className="text-muted">
        {t('SHIFT_TIME_REPORT_PLANNED', {
          start: wallClockTime(shift.startsAt),
          end: wallClockTime(shift.endsAt),
          break: shift.breakMinutes,
        })}
      </p>
      <div className="report-time-modal__row">
        <span>{t('SHIFT_TIME_REPORT_WORKED')}</span>
        <span>
          <TimePicker
            format="HH:mm"
            minuteStep={5}
            allowClear={false}
            value={start}
            onChange={setStart}
          />
          {' — '}
          <TimePicker
            format="HH:mm"
            minuteStep={5}
            allowClear={false}
            value={end}
            onChange={setEnd}
          />
        </span>
      </div>
      <div className="report-time-modal__row">
        <span>{t('SHIFT_PLANNING_BREAK_MINUTES')}</span>
        <InputNumber
          min={0}
          step={5}
          value={breakMinutes}
          onChange={v => setBreakMinutes(v ?? 0)}
          addonAfter={t('SHIFT_COMPLIANCE_UNIT_min')}
        />
      </div>
      <Input.TextArea
        rows={2}
        placeholder={t('SHIFT_TIME_REPORT_COMMENT_PLACEHOLDER')}
        value={comment}
        onChange={e => setComment(e.target.value)}
      />
    </Modal>
  );
}
