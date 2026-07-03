import React, { useEffect, useState } from 'react';
import { App, Button, ColorPicker, Modal, Space, Tooltip } from 'antd';
import { SettingOutlined, UndoOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  useGetShiftSettingsQuery,
  usePutShiftSettingsMutation,
} from '../../../api/slice';
import { shiftTypeColor } from '../utils/shiftTypeColor';

type Props = {
  shiftTypes: string[];
};

export default function ShiftSettingsModal({ shiftTypes }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [open, setOpen] = useState(false);
  const [colors, setColors] = useState<Record<string, string>>({});

  const { data } = useGetShiftSettingsQuery();
  const [putShiftSettings, { isLoading }] = usePutShiftSettingsMutation();

  useEffect(() => {
    if (open) {
      // Seed the form with the effective color of every type (custom or default),
      // so the picker always reflects what's actually shown on the grid
      const seed: Record<string, string> = {};
      shiftTypes.forEach(type => {
        seed[type] = shiftTypeColor(type, data?.typeColors);
      });
      setColors(seed);
    }
  }, [open, shiftTypes, data]);

  const onSave = async () => {
    try {
      await putShiftSettings({ typeColors: colors }).unwrap();
      message.success(t('SHIFT_PLANNING_SAVED'));
      setOpen(false);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  return (
    <>
      <Button icon={<SettingOutlined />} onClick={() => setOpen(true)}>
        {t('SHIFT_PLANNING_SETTINGS')}
      </Button>
      <Modal
        open={open}
        title={t('SHIFT_PLANNING_SETTINGS')}
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
            onClick={onSave}>
            {t('SHIFT_PLANNING_SAVE')}
          </Button>,
        ]}>
        <p>{t('SHIFT_PLANNING_TYPE_COLORS_HELP')}</p>
        <Space direction="vertical" style={{ width: '100%' }}>
          {shiftTypes.map(type => (
            <div
              key={type}
              style={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
              }}>
              <span>{type}</span>
              <Space>
                <ColorPicker
                  disabledAlpha
                  showText
                  value={colors[type]}
                  onChangeComplete={color =>
                    setColors(c => ({ ...c, [type]: color.toHexString() }))
                  }
                />
                <Tooltip title={t('SHIFT_PLANNING_RESET_COLOR')}>
                  <Button
                    type="text"
                    size="small"
                    icon={<UndoOutlined />}
                    disabled={colors[type] === shiftTypeColor(type)}
                    onClick={() =>
                      setColors(c => ({
                        ...c,
                        [type]: shiftTypeColor(type),
                      }))
                    }
                  />
                </Tooltip>
              </Space>
            </div>
          ))}
        </Space>
      </Modal>
    </>
  );
}
