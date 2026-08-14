import React, { useEffect, useState } from 'react';
import { App, Button, Input, Modal } from 'antd';
import moment from 'moment';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useCreateShiftTemplateMutation } from '../../../api/slice';

// moment (locale-aware, see utils/antd.js) is used for display,
// parsing the plain date to stay independent of the browser timezone
const formatWeek = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
  open: boolean;
  onClose: () => void;
};

/**
 * Snapshots the currently displayed week's shifts (shape + assignees) into a
 * named, reusable template — see LoadTemplateModal to apply one later.
 * Triggered from WeekActionsMenu.
 */
export default function SaveAsTemplateModal({ weekStart, open, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [name, setName] = useState('');

  const [createShiftTemplate, { isLoading }] = useCreateShiftTemplateMutation();

  useEffect(() => {
    if (open) {
      setName('');
    }
  }, [open]);

  const onSave = async () => {
    if (!name.trim()) {
      return;
    }
    try {
      await createShiftTemplate({
        name: name.trim(),
        week: weekStart.format('YYYY-MM-DD'),
      }).unwrap();
      message.success(t('SHIFT_TEMPLATE_SAVED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={open}
      title={t('SHIFT_TEMPLATE_SAVE_TITLE', { week: formatWeek(weekStart) })}
      onCancel={onClose}
      destroyOnHidden
      footer={[
        <Button key="cancel" onClick={onClose}>
          {t('SHIFT_PLANNING_CANCEL')}
        </Button>,
        <Button
          key="save"
          type="primary"
          loading={isLoading}
          disabled={!name.trim()}
          onClick={onSave}>
          {t('SHIFT_PLANNING_SAVE')}
        </Button>,
      ]}>
      <p className="text-muted">{t('SHIFT_TEMPLATE_SAVE_HELP')}</p>
      <Input
        autoFocus
        placeholder={t('SHIFT_TEMPLATE_NAME_PLACEHOLDER')}
        value={name}
        maxLength={255}
        onChange={e => setName(e.target.value)}
        onPressEnter={onSave}
      />
    </Modal>
  );
}
