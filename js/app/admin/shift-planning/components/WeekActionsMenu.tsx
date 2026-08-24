import React, { useState } from 'react';
import { App, Button, Dropdown } from 'antd';
import type { MenuProps } from 'antd';
import {
  CopyOutlined,
  DownOutlined,
  FolderOpenOutlined,
  SaveOutlined,
} from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import moment from 'moment';
import { useTranslation } from 'react-i18next';
import { useCopyWeekMutation } from '../../../api/slice';
import SaveAsTemplateModal from './SaveAsTemplateModal';
import LoadTemplateModal from './LoadTemplateModal';

// moment (locale-aware, see utils/antd.js) is used for display,
// parsing the plain date to stay independent of the browser timezone
const formatDay = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
};

/**
 * Groups the three ways to populate a week's shifts from elsewhere — copy
 * last week as-is, load a saved template, or save this week as a new
 * template — under one dropdown, so the toolbar doesn't carry three
 * near-identical buttons side by side.
 */
export default function WeekActionsMenu({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message, modal } = App.useApp();

  const [saveOpen, setSaveOpen] = useState(false);
  const [loadOpen, setLoadOpen] = useState(false);

  const [copyWeek, { isLoading: isCopying }] = useCopyWeekMutation();

  const onCopyLastWeek = () => {
    const sourceWeek = weekStart.subtract(7, 'day');

    modal.confirm({
      title: t('SHIFT_PLANNING_COPY_CONFIRM', {
        source: formatDay(sourceWeek),
        target: formatDay(weekStart),
      }),
      content: t('SHIFT_PLANNING_COPY_CONFIRM_NOTE'),
      onOk: async () => {
        try {
          await copyWeek({
            sourceWeek: sourceWeek.format('YYYY-MM-DD'),
            targetWeek: weekStart.format('YYYY-MM-DD'),
          }).unwrap();
          message.success(t('SHIFT_PLANNING_COPY_SUCCESS'));
        } catch (e) {
          message.error(t('SHIFT_PLANNING_ERROR'));
        }
      },
    });
  };

  const items: MenuProps['items'] = [
    {
      key: 'copy',
      icon: <CopyOutlined />,
      label: t('SHIFT_PLANNING_COPY_LAST_WEEK'),
      onClick: onCopyLastWeek,
    },
    {
      key: 'load',
      icon: <FolderOpenOutlined />,
      label: t('SHIFT_TEMPLATE_LOAD'),
      onClick: () => setLoadOpen(true),
    },
    {
      key: 'save',
      icon: <SaveOutlined />,
      label: t('SHIFT_TEMPLATE_SAVE'),
      onClick: () => setSaveOpen(true),
    },
  ];

  return (
    <>
      <Dropdown menu={{ items }} trigger={['click']}>
        <Button loading={isCopying} icon={<DownOutlined />} iconPosition="end">
          {t('SHIFT_PLANNING_WEEK_ACTIONS')}
        </Button>
      </Dropdown>
      <SaveAsTemplateModal
        weekStart={weekStart}
        open={saveOpen}
        onClose={() => setSaveOpen(false)}
      />
      <LoadTemplateModal
        weekStart={weekStart}
        open={loadOpen}
        onClose={() => setLoadOpen(false)}
      />
    </>
  );
}
