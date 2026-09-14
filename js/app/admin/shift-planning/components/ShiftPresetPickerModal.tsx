import React, { useState } from 'react';
import { App, Button, Empty, Modal, Popconfirm, Tag } from 'antd';
import { DeleteOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  useDeleteShiftPresetMutation,
  useGetShiftPresetsQuery,
} from '../../../api/slice';
import { ShiftActivity, ShiftPreset, Uri } from '../../../api/types';
import { activityColor } from '../utils/shiftTypeColor';
import { activityLabel } from '../utils/activityLabel';

type Props = {
  activities: ShiftActivity[];
  open: boolean;
  onClose: () => void;
  onSelect: (preset: ShiftPreset) => void;
};

// "9:00" - "17:30" -> "8h30"
const formatDuration = (startTime: string, endTime: string): string => {
  const [sh, sm] = startTime.split(':').map(Number);
  const [eh, em] = endTime.split(':').map(Number);
  const minutes = eh * 60 + em - (sh * 60 + sm);
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  return m > 0 ? `${h}h${String(m).padStart(2, '0')}` : `${h}h`;
};

/**
 * "New shift from template" gallery: pick a previously saved ShiftPreset
 * (see SaveShiftPresetModal) to prefill ShiftModal with — everything but who
 * it's assigned to. Triggered from the planning toolbar's "New shift" split
 * button.
 */
export default function ShiftPresetPickerModal({
  activities,
  open,
  onClose,
  onSelect,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [selected, setSelected] = useState<Uri | null>(null);

  const { data: presets, isFetching } = useGetShiftPresetsQuery(undefined, {
    skip: !open,
  });
  const [deleteShiftPreset] = useDeleteShiftPresetMutation();

  const selectedPreset = (presets ?? []).find(p => p['@id'] === selected);

  const onClosed = () => {
    setSelected(null);
    onClose();
  };

  const onUse = () => {
    if (!selectedPreset) {
      return;
    }
    onSelect(selectedPreset);
    setSelected(null);
  };

  const onDelete = async (uri: Uri) => {
    try {
      await deleteShiftPreset(uri).unwrap();
      if (selected === uri) {
        setSelected(null);
      }
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <Modal
      open={open}
      title={t('SHIFT_PRESET_PICKER_TITLE')}
      onCancel={onClosed}
      destroyOnHidden
      width={640}
      footer={[
        <Button key="cancel" onClick={onClosed}>
          {t('SHIFT_PLANNING_CANCEL')}
        </Button>,
        <Button key="use" type="primary" disabled={!selectedPreset} onClick={onUse}>
          {t('SHIFT_PRESET_USE')}
        </Button>,
      ]}>
      {!isFetching && (presets ?? []).length === 0 ? (
        <Empty description={t('SHIFT_PRESET_EMPTY')} />
      ) : (
        <>
          <div className="shift-preset-grid">
            {(presets ?? []).map(preset => (
              <button
                type="button"
                key={preset['@id']}
                className={`shift-preset-tile ${
                  selected === preset['@id'] ? 'shift-preset-tile--selected' : ''
                }`}
                style={{ backgroundColor: activityColor(preset.activity, activities) }}
                onClick={() => setSelected(preset['@id'])}>
                <span className="shift-preset-tile__name">{preset.name}</span>
                <span className="shift-preset-tile__time">
                  {preset.startTime} - {preset.endTime}
                </span>
              </button>
            ))}
          </div>
          {selectedPreset && (
            <div className="shift-preset-details">
              <dl>
                <dt>{t('SHIFT_PRESET_DETAIL_TYPE')}</dt>
                <dd>{activityLabel(selectedPreset.activity, activities, t)}</dd>
                <dt>{t('SHIFT_PRESET_DETAIL_HOURS')}</dt>
                <dd>
                  {selectedPreset.startTime} - {selectedPreset.endTime}
                </dd>
                <dt>{t('SHIFT_PRESET_DETAIL_DURATION')}</dt>
                <dd>{formatDuration(selectedPreset.startTime, selectedPreset.endTime)}</dd>
                {selectedPreset.breakMinutes > 0 && (
                  <>
                    <dt>{t('SHIFT_PLANNING_BREAK_MINUTES')}</dt>
                    <dd>{selectedPreset.breakMinutes} min</dd>
                  </>
                )}
                <dt>{t('SHIFT_PLANNING_SLOTS')}</dt>
                <dd>{selectedPreset.slots}</dd>
                {selectedPreset.requiredSkills.length > 0 && (
                  <>
                    <dt>{t('SHIFT_PLANNING_REQUIRED_SKILLS')}</dt>
                    <dd>
                      {selectedPreset.requiredSkills.map(s => (
                        <Tag key={s['@id']}>{s.name}</Tag>
                      ))}
                    </dd>
                  </>
                )}
                {selectedPreset.comment && (
                  <>
                    <dt>{t('SHIFT_PLANNING_COMMENT')}</dt>
                    <dd>{selectedPreset.comment}</dd>
                  </>
                )}
              </dl>
              <Popconfirm
                title={t('SHIFT_PRESET_DELETE_CONFIRM', { name: selectedPreset.name })}
                onConfirm={() => onDelete(selectedPreset['@id'])}>
                <Button danger size="small" icon={<DeleteOutlined />}>
                  {t('SHIFT_PLANNING_DELETE')}
                </Button>
              </Popconfirm>
            </div>
          )}
        </>
      )}
    </Modal>
  );
}
