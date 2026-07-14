import React, { useEffect, useState } from 'react';
import {
  App,
  Button,
  ColorPicker,
  Divider,
  InputNumber,
  Modal,
  Select,
  Slider,
  Space,
  Tooltip,
} from 'antd';
import { SettingOutlined, UndoOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import {
  useGetShiftSettingsQuery,
  usePutShiftSettingsMutation,
} from '../../../api/slice';
import { LegalRules } from '../../../api/types';
import { shiftTypeColor } from '../utils/shiftTypeColor';

type Props = {
  shiftTypes: string[];
};

// Editable legal rules, in display order, with their unit and input step
const LEGAL_RULES: { key: string; unit: 'h' | 'min' | 'weeks' | 'days'; step: number }[] = [
  { key: 'maxDailyHours', unit: 'h', step: 0.5 },
  { key: 'maxWeeklyHours', unit: 'h', step: 1 },
  { key: 'maxAvgWeeklyHours', unit: 'h', step: 1 },
  { key: 'avgWeeklyHoursWindowWeeks', unit: 'weeks', step: 1 },
  { key: 'minDailyRestHours', unit: 'h', step: 0.5 },
  { key: 'minWeeklyRestHours', unit: 'h', step: 1 },
  { key: 'breakThresholdHours', unit: 'h', step: 0.5 },
  { key: 'minBreakMinutes', unit: 'min', step: 5 },
  { key: 'maxConsecutiveDays', unit: 'days', step: 1 },
];

export default function ShiftSettingsModal({ shiftTypes }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [open, setOpen] = useState(false);
  const [colors, setColors] = useState<Record<string, string>>({});
  const [throughput, setThroughput] = useState<number>(2.5);
  const [serviceLevel, setServiceLevel] = useState<number>(0.8);
  const [legalTemplate, setLegalTemplate] = useState<string | null>(null);
  const [legalOverrides, setLegalOverrides] = useState<LegalRules>({});

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
      if (data) {
        setThroughput(data.throughput);
        setServiceLevel(data.serviceLevel);
        setLegalTemplate(data.legal?.template ?? null);
        setLegalOverrides(data.legal?.rules ?? {});
      }
    }
  }, [open, shiftTypes, data]);

  const onSave = async () => {
    try {
      await putShiftSettings({
        typeColors: colors,
        throughput,
        serviceLevel,
        legal: { template: legalTemplate, rules: legalOverrides },
      }).unwrap();
      message.success(t('SHIFT_PLANNING_SAVED'));
      setOpen(false);
    } catch (e) {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const templateDefaults = legalTemplate
    ? (data?.legalTemplates?.[legalTemplate]?.rules ?? {})
    : {};

  // Effective value shown for a rule: the override if present (null = rule
  // disabled), otherwise the template default
  const legalValue = (key: string): number | null =>
    key in legalOverrides ? legalOverrides[key] : (templateDefaults[key] ?? null);

  const setLegalValue = (key: string, value: number | null) => {
    setLegalOverrides(overrides => {
      // Back to the template default: drop the override so future template
      // updates apply
      if (value !== null && value === templateDefaults[key]) {
        const { [key]: _removed, ...rest } = overrides;
        return rest;
      }
      return { ...overrides, [key]: value };
    });
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

        <Divider />

        <p>{t('SHIFT_PLANNING_DEMAND_SETTINGS_HELP')}</p>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginBottom: 12,
          }}>
          <span>{t('SHIFT_PLANNING_THROUGHPUT')}</span>
          <InputNumber
            min={0.5}
            max={20}
            step={0.5}
            value={throughput}
            onChange={v => v != null && setThroughput(v)}
            addonAfter={t('SHIFT_PLANNING_PER_HOUR')}
          />
        </div>
        <div>
          <span>
            {t('SHIFT_PLANNING_SERVICE_LEVEL')}:{' '}
            <strong>{Math.round(serviceLevel * 100)}%</strong>
          </span>
          <Slider
            min={50}
            max={99}
            value={Math.round(serviceLevel * 100)}
            onChange={v => setServiceLevel(v / 100)}
            tooltip={{ formatter: v => `${v}%` }}
          />
        </div>

        <Divider />

        <p>{t('SHIFT_COMPLIANCE_SETTINGS_HELP')}</p>
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'space-between',
            marginBottom: 12,
          }}>
          <span>{t('SHIFT_COMPLIANCE_TEMPLATE')}</span>
          <Select
            style={{ minWidth: 260 }}
            value={legalTemplate ?? 'none'}
            onChange={value => {
              setLegalTemplate(value === 'none' ? null : value);
              setLegalOverrides({});
            }}
            options={[
              { value: 'none', label: t('SHIFT_COMPLIANCE_TEMPLATE_NONE') },
              ...Object.keys(data?.legalTemplates ?? {}).map(id => ({
                value: id,
                label: t(`SHIFT_COMPLIANCE_TEMPLATE_${id}`, {
                  defaultValue: id,
                }),
              })),
            ]}
          />
        </div>
        {legalTemplate && (
          <>
            <p className="text-muted">{t('SHIFT_COMPLIANCE_RULES_HELP')}</p>
            <Space direction="vertical" style={{ width: '100%' }}>
              {LEGAL_RULES.map(({ key, unit, step }) => (
                <div
                  key={key}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                  }}>
                  <span>{t(`SHIFT_COMPLIANCE_RULE_${key}`)}</span>
                  <Space>
                    <InputNumber
                      min={0}
                      step={step}
                      value={legalValue(key)}
                      placeholder={t('SHIFT_COMPLIANCE_RULE_OFF')}
                      onChange={v => setLegalValue(key, v)}
                      addonAfter={t(`SHIFT_COMPLIANCE_UNIT_${unit}`)}
                      style={{ width: 160 }}
                    />
                    <Tooltip title={t('SHIFT_COMPLIANCE_RESET_RULE')}>
                      <Button
                        type="text"
                        size="small"
                        icon={<UndoOutlined />}
                        disabled={!(key in legalOverrides)}
                        onClick={() =>
                          setLegalOverrides(({ [key]: _removed, ...rest }) => rest)
                        }
                      />
                    </Tooltip>
                  </Space>
                </div>
              ))}
            </Space>
          </>
        )}
      </Modal>
    </>
  );
}
