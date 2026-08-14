import React, { useEffect, useState } from 'react';
import {
  App,
  Button,
  Checkbox,
  Empty,
  List,
  Modal,
  Popconfirm,
  Select,
  Tag,
} from 'antd';
import { DeleteOutlined } from '@ant-design/icons';
import moment from 'moment';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import {
  useApplyShiftTemplateMutation,
  useDeleteShiftTemplateMutation,
  useGetShiftTemplatesQuery,
} from '../../../api/slice';
import { Uri } from '../../../api/types';

const formatWeek = (day: Dayjs) => moment(day.format('YYYY-MM-DD')).format('LL');

type Props = {
  weekStart: Dayjs;
  open: boolean;
  onClose: () => void;
};

/**
 * Applies a previously saved ShiftTemplate to the currently displayed week —
 * see SaveAsTemplateModal for how templates get created. Also lets a
 * dispatcher delete templates they no longer need. Triggered from
 * WeekActionsMenu.
 */
export default function LoadTemplateModal({ weekStart, open, onClose }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [selected, setSelected] = useState<Uri | null>(null);
  const [includeAssignees, setIncludeAssignees] = useState(false);

  const { data: templates, isFetching } = useGetShiftTemplatesQuery(undefined, {
    skip: !open,
  });
  const [applyShiftTemplate, { isLoading: isApplying }] =
    useApplyShiftTemplateMutation();
  const [deleteShiftTemplate] = useDeleteShiftTemplateMutation();

  useEffect(() => {
    if (open) {
      setSelected(null);
      setIncludeAssignees(false);
    }
  }, [open]);

  const selectedTemplate = (templates ?? []).find(
    tpl => tpl['@id'] === selected,
  );

  const onApply = async () => {
    if (!selected) {
      return;
    }
    try {
      const result = await applyShiftTemplate({
        uri: selected,
        targetWeek: weekStart.format('YYYY-MM-DD'),
        includeAssignees,
      }).unwrap();
      message.success(
        t('SHIFT_TEMPLATE_APPLY_SUCCESS', { count: result.created }),
      );
      onClose();
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const onDelete = async (uri: Uri) => {
    try {
      await deleteShiftTemplate(uri).unwrap();
      if (selected === uri) {
        setSelected(null);
      }
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={open}
      title={t('SHIFT_TEMPLATE_LOAD_TITLE', { week: formatWeek(weekStart) })}
      onCancel={onClose}
      destroyOnHidden
      footer={[
        <Button key="cancel" onClick={onClose}>
          {t('SHIFT_PLANNING_CANCEL')}
        </Button>,
        <Button
          key="apply"
          type="primary"
          disabled={!selected}
          loading={isApplying}
          onClick={onApply}>
          {t('SHIFT_TEMPLATE_APPLY')}
        </Button>,
      ]}>
      {!isFetching && (templates ?? []).length === 0 ? (
        <Empty description={t('SHIFT_TEMPLATE_EMPTY')} />
      ) : (
        <>
          <Select
            style={{ width: '100%' }}
            loading={isFetching}
            placeholder={t('SHIFT_TEMPLATE_SELECT_PLACEHOLDER')}
            value={selected}
            onChange={setSelected}
            optionFilterProp="label"
            options={(templates ?? []).map(tpl => ({
              value: tpl['@id'],
              label: t('SHIFT_TEMPLATE_OPTION_LABEL', {
                name: tpl.name,
                count: tpl.shiftCount,
              }),
            }))}
          />
          {selectedTemplate?.hasAssignees && (
            <div className="mt-2">
              <Checkbox
                checked={includeAssignees}
                onChange={e => setIncludeAssignees(e.target.checked)}>
                {t('SHIFT_TEMPLATE_INCLUDE_ASSIGNEES')}
              </Checkbox>
            </div>
          )}
          <List
            className="mt-3 shift-template-list"
            size="small"
            dataSource={templates ?? []}
            renderItem={tpl => (
              <List.Item
                actions={[
                  <Popconfirm
                    key="delete"
                    title={t('SHIFT_TEMPLATE_DELETE_CONFIRM', {
                      name: tpl.name,
                    })}
                    onConfirm={() => onDelete(tpl['@id'])}>
                    <Button
                      type="text"
                      danger
                      size="small"
                      icon={<DeleteOutlined />}
                    />
                  </Popconfirm>,
                ]}>
                <span>
                  {tpl.name}{' '}
                  <Tag>
                    {t('SHIFT_TEMPLATE_SHIFT_COUNT', {
                      count: tpl.shiftCount,
                    })}
                  </Tag>
                  {tpl.hasAssignees && (
                    <Tag color="blue">{t('SHIFT_TEMPLATE_HAS_ASSIGNEES')}</Tag>
                  )}
                </span>
              </List.Item>
            )}
          />
        </>
      )}
    </Modal>
  );
}
