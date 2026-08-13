import React, { useState } from 'react';
import { App, Button, Input, Modal } from 'antd';
import { SaveOutlined } from '@ant-design/icons';
import moment from 'moment';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useCreateShiftTemplateMutation } from '../../../api/slice';

// moment (locale-aware, see utils/antd.js) is used for display,
// parsing the plain date to stay independent of the browser timezone
const formatWeek = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
};

/**
 * Snapshots the currently displayed week's shifts (shape + assignees) into a
 * named, reusable template — see LoadTemplateButton to apply one later.
 */
export default function SaveAsTemplateButton({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [open, setOpen] = useState(false);
  const [name, setName] = useState('');

  const [createShiftTemplate, { isLoading }] = useCreateShiftTemplateMutation();

  const onOpen = () => {
    setName('');
    setOpen(true);
  };

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
      setOpen(false);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <>
      <Button icon={<SaveOutlined />} onClick={onOpen}>
        {t('SHIFT_TEMPLATE_SAVE')}
      </Button>
      <Modal
        open={open}
        title={t('SHIFT_TEMPLATE_SAVE_TITLE', { week: formatWeek(weekStart) })}
        onCancel={() => setOpen(false)}
        destroyOnHidden
        footer={[
          <Button key="cancel" onClick={() => setOpen(false)}>
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
    </>
  );
}
