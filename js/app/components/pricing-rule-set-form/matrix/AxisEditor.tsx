import React from 'react';
import { Button, Input, InputNumber, Select, Space, Table } from 'antd';
import { DeleteOutlined, PlusOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { v4 as uuidv4 } from 'uuid';

import {
  MatrixAddressSource,
  MatrixAxis,
  MatrixAxisEntry,
  MatrixAxisVariable,
} from '../../../api/types';
import { useGetTimeSlotsQuery, useGetZonesQuery } from '../../../api/slice';
import {
  AXIS_VARIABLES,
  VARIABLE_LABEL_KEYS,
  isNumeric,
  toDisplayBound,
  toStoredBound,
} from './axis';

type Props = {
  axis: MatrixAxis;
  onChange: (axis: MatrixAxis) => void;
  title: string;
  disabled?: boolean;
};

const ADDRESS_SOURCES: MatrixAddressSource[] = ['task', 'pickup', 'dropoff'];

const AxisEditor = ({ axis, onChange, title, disabled = false }: Props) => {
  const { t } = useTranslation();

  const { data: zones } = useGetZonesQuery(undefined, {
    skip: axis.variable !== 'zone',
  });
  const { data: timeSlots } = useGetTimeSlotsQuery(undefined, {
    skip: axis.variable !== 'time_slot',
  });

  const numeric = isNumeric(axis.variable);

  const updateEntry = (index: number, patch: Partial<MatrixAxisEntry>) => {
    const entries = axis.entries.map((entry, i) =>
      i === index ? { ...entry, ...patch } : entry,
    );
    onChange({ ...axis, entries });
  };

  const addEntry = () => {
    // The key is what ties a cell to the rule it generated, so it never changes
    // once created: reordering or inserting entries must not disturb the others
    const entry: MatrixAxisEntry = { key: uuidv4(), label: '' };
    onChange({ ...axis, entries: [...axis.entries, entry] });
  };

  const removeEntry = (index: number) => {
    onChange({ ...axis, entries: axis.entries.filter((_, i) => i !== index) });
  };

  const changeVariable = (variable: MatrixAxisVariable) => {
    // Entries of a numeric axis mean nothing on an enumerated one, and the other
    // way round, so the entries start over when the variable changes
    onChange({
      variable,
      entries: [],
      ...(variable === 'zone' ? { addressSource: 'task' as const } : {}),
    });
  };

  const columns = [
    {
      title: t('PRICING_MATRIX_ENTRY_LABEL'),
      key: 'label',
      render: (_: unknown, entry: MatrixAxisEntry, index: number) => (
        <Input
          value={entry.label ?? ''}
          disabled={disabled}
          placeholder={t('PRICING_MATRIX_ENTRY_LABEL_PLACEHOLDER')}
          onChange={e => updateEntry(index, { label: e.target.value })}
        />
      ),
    },
    numeric
      ? {
          title: t('PRICING_MATRIX_ENTRY_RANGE'),
          key: 'range',
          render: (_: unknown, entry: MatrixAxisEntry, index: number) => (
            <Space>
              <InputNumber
                value={toDisplayBound(entry.min, axis.variable)}
                disabled={disabled}
                placeholder={t('PRICING_MATRIX_ENTRY_MIN')}
                onChange={value =>
                  updateEntry(index, {
                    min: toStoredBound(value as number, axis.variable),
                  })
                }
              />
              <span>→</span>
              <InputNumber
                value={toDisplayBound(entry.max, axis.variable)}
                disabled={disabled}
                placeholder={t('PRICING_MATRIX_ENTRY_MAX')}
                onChange={value =>
                  updateEntry(index, {
                    max: toStoredBound(value as number, axis.variable),
                  })
                }
              />
            </Space>
          ),
        }
      : {
          title: t('PRICING_MATRIX_ENTRY_VALUE'),
          key: 'value',
          render: (_: unknown, entry: MatrixAxisEntry, index: number) => (
            <Select
              style={{ minWidth: 200 }}
              value={entry.value ?? undefined}
              disabled={disabled}
              placeholder={t('PRICING_MATRIX_ENTRY_VALUE')}
              onChange={value => updateEntry(index, { value })}
              options={
                axis.variable === 'zone'
                  ? (zones ?? []).map(zone => ({
                      label: zone.name,
                      value: zone.name,
                    }))
                  : (timeSlots ?? []).map(timeSlot => ({
                      label: timeSlot.name,
                      value: timeSlot['@id'],
                    }))
              }
            />
          ),
        },
    {
      title: '',
      key: 'actions',
      width: 50,
      render: (_: unknown, __: MatrixAxisEntry, index: number) => (
        <Button
          type="text"
          danger
          disabled={disabled}
          icon={<DeleteOutlined />}
          onClick={() => removeEntry(index)}
        />
      ),
    },
  ];

  return (
    <div className="mb-4">
      <h5>{title}</h5>
      <Space className="mb-2" wrap>
        <Select
          style={{ minWidth: 260 }}
          value={axis.variable}
          disabled={disabled}
          onChange={changeVariable}
          options={AXIS_VARIABLES.map(variable => ({
            label: t(VARIABLE_LABEL_KEYS[variable]),
            value: variable,
          }))}
        />
        {axis.variable === 'zone' ? (
          <Select
            style={{ minWidth: 180 }}
            value={axis.addressSource ?? 'task'}
            disabled={disabled}
            onChange={addressSource => onChange({ ...axis, addressSource })}
            options={ADDRESS_SOURCES.map(source => ({
              label: t(`PRICING_MATRIX_ADDRESS_SOURCE_${source}`),
              value: source,
            }))}
          />
        ) : null}
      </Space>
      <Table
        size="small"
        rowKey={entry => entry.key}
        dataSource={axis.entries}
        columns={columns}
        pagination={false}
        locale={{ emptyText: t('PRICING_MATRIX_NO_ENTRY') }}
      />
      <Button
        className="mt-2"
        size="small"
        icon={<PlusOutlined />}
        disabled={disabled}
        onClick={addEntry}>
        {t('PRICING_MATRIX_ADD_ENTRY')}
      </Button>
    </div>
  );
};

export default AxisEditor;
