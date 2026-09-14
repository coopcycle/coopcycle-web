import React, { useEffect, useState } from 'react';
import { App, Button, Input, Modal } from 'antd';
import { useTranslation } from 'react-i18next';
import { useCreateShiftPresetMutation } from '../../../api/slice';
import { CreateShiftPresetRequest } from '../../../api/types';

type Props = {
  /** The shift form's current values, everything but the name and assignees */
  snapshot: Omit<CreateShiftPresetRequest, 'name'>;
  open: boolean;
  onClose: () => void;
};

/**
 * Names and saves the currently edited shift's shape (activity, times,
 * slots, break, skills — never who it's assigned to) as a reusable
 * ShiftPreset. Triggered from ShiftModal's "Save as template" button; see
 * ShiftPresetPickerModal for applying one back later.
 */
export default function SaveShiftPresetModal({ snapshot, open, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [name, setName] = useState('');

  const [createShiftPreset, { isLoading }] = useCreateShiftPresetMutation();

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
      await createShiftPreset({ ...snapshot, name: name.trim() }).unwrap();
      message.success(t('SHIFT_PRESET_SAVED'));
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={open}
      title={t('SHIFT_PRESET_SAVE_TITLE')}
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
      <p className="text-muted">{t('SHIFT_PRESET_SAVE_HELP')}</p>
      <Input
        autoFocus
        placeholder={t('SHIFT_PRESET_NAME_PLACEHOLDER')}
        value={name}
        maxLength={255}
        onChange={e => setName(e.target.value)}
        onPressEnter={onSave}
      />
    </Modal>
  );
}
