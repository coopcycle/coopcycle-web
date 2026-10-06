import React from 'react';
import { Button, Input, InputNumber, Select, Tooltip } from 'antd';
import { CloseOutlined, PlusOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import { v4 as uuidv4 } from 'uuid';

import {
  MatrixAxis,
  MatrixAxisEntry,
  MatrixAxisVariable,
} from '../../../api/types';
import { useGetTimeSlotsQuery, useGetZonesQuery } from '../../../api/slice';
import {
  AXIS_VARIABLES,
  VARIABLE_LABEL_KEYS,
  cellKey,
  isNumeric,
  toDisplayBound,
  toStoredBound,
} from './axis';

type Props = {
  rowAxis: MatrixAxis;
  columnAxis: MatrixAxis;
  cells: Record<string, number>;
  onRowAxisChange: (axis: MatrixAxis) => void;
  onColumnAxisChange: (axis: MatrixAxis) => void;
  onCellChange: (rowKey: string, columnKey: string, euros: number | null) => void;
};

/*
  The grid is edited in place: each row and column header carries its own label,
  its range or value, and the button that removes it, and a last cell on each axis
  adds one more. There is no separate list of rows and columns to keep in sync
  with what the grid shows.
*/
const PricingMatrixGrid = ({
  rowAxis,
  columnAxis,
  cells,
  onRowAxisChange,
  onColumnAxisChange,
  onCellChange,
}: Props) => {
  const { t } = useTranslation();

  const needsZones =
    rowAxis.variable === 'zone' || columnAxis.variable === 'zone';
  const needsTimeSlots =
    rowAxis.variable === 'time_slot' || columnAxis.variable === 'time_slot';

  const { data: zones } = useGetZonesQuery(undefined, { skip: !needsZones });
  const { data: timeSlots } = useGetTimeSlotsQuery(undefined, {
    skip: !needsTimeSlots,
  });

  const updateEntry = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
    index: number,
    patch: Partial<MatrixAxisEntry>,
  ) => {
    onChange({
      ...axis,
      entries: axis.entries.map((entry, i) =>
        i === index ? { ...entry, ...patch } : entry,
      ),
    });
  };

  const addEntry = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
  ) => {
    onChange({
      ...axis,
      entries: [...axis.entries, { key: uuidv4(), label: '' }],
    });
  };

  const removeEntry = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
    index: number,
  ) => {
    onChange({
      ...axis,
      entries: axis.entries.filter((_, i) => i !== index),
    });
  };

  const changeVariable = (
    onChange: (axis: MatrixAxis) => void,
    entries: MatrixAxisEntry[],
    variable: MatrixAxisVariable,
  ) => {
    // Ranges mean nothing on an enumerated axis and the other way round, so the
    // bounds and values are dropped — the rows and columns themselves stay
    onChange({
      variable,
      entries: entries.map(entry => ({ key: entry.key, label: entry.label })),
      ...(variable === 'zone' ? { addressSource: 'task' as const } : {}),
    });
  };

  const valueOptions = (axis: MatrixAxis) =>
    axis.variable === 'zone'
      ? (zones ?? []).map(zone => ({ label: zone.name, value: zone.name }))
      : (timeSlots ?? []).map(timeSlot => ({
          label: timeSlot.name,
          value: timeSlot['@id'],
        }));

  const entryFields = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
    entry: MatrixAxisEntry,
    index: number,
  ) => (
    <div className="pricing-matrix__header-fields">
      <Input
        size="small"
        value={entry.label ?? ''}
        placeholder={t('PRICING_MATRIX_ENTRY_LABEL_PLACEHOLDER')}
        onChange={e =>
          updateEntry(axis, onChange, index, { label: e.target.value })
        }
      />
      {isNumeric(axis.variable) ? (
        <div className="pricing-matrix__bounds">
          <InputNumber
            size="small"
            value={toDisplayBound(entry.min, axis.variable)}
            placeholder={t('PRICING_MATRIX_ENTRY_MIN')}
            onChange={value =>
              updateEntry(axis, onChange, index, {
                min: toStoredBound(value as number, axis.variable),
              })
            }
          />
          <span className="pricing-matrix__bounds-arrow">→</span>
          <InputNumber
            size="small"
            value={toDisplayBound(entry.max, axis.variable)}
            placeholder={t('PRICING_MATRIX_ENTRY_MAX')}
            onChange={value =>
              updateEntry(axis, onChange, index, {
                max: toStoredBound(value as number, axis.variable),
              })
            }
          />
        </div>
      ) : (
        <Select
          size="small"
          style={{ width: '100%' }}
          value={entry.value ?? undefined}
          placeholder={t('PRICING_MATRIX_ENTRY_VALUE')}
          onChange={value => updateEntry(axis, onChange, index, { value })}
          options={valueOptions(axis)}
        />
      )}
    </div>
  );

  const removeButton = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
    index: number,
    label: string,
  ) => (
    <Tooltip title={label}>
      <Button
        className="pricing-matrix__remove"
        type="text"
        size="small"
        danger
        aria-label={label}
        icon={<CloseOutlined />}
        onClick={() => removeEntry(axis, onChange, index)}
      />
    </Tooltip>
  );

  const variableSelect = (
    axis: MatrixAxis,
    onChange: (axis: MatrixAxis) => void,
    label: string,
    className: string,
  ) => (
    <div className={className}>
      <span className="pricing-matrix__axis-label">{label}</span>
      <Select
        size="small"
        style={{ width: '100%' }}
        value={axis.variable}
        onChange={variable => changeVariable(onChange, axis.entries, variable)}
        options={AXIS_VARIABLES.map(item => ({
          label: t(VARIABLE_LABEL_KEYS[item]),
          value: item,
        }))}
      />
    </div>
  );

  return (
    <div className="pricing-matrix__scroll">
      <table className="pricing-matrix" data-testid="pricing-matrix-grid">
        <thead>
          <tr>
            <th className="pricing-matrix__corner">
              {/*
                Split the way the grid reads: the columns run off to the right, the
                rows run down the left, so each sits on its own side of the diagonal
              */}
              <svg
                className="pricing-matrix__corner-diagonal"
                viewBox="0 0 100 100"
                preserveAspectRatio="none"
                aria-hidden="true">
                <line
                  x1="0"
                  y1="0"
                  x2="100"
                  y2="100"
                  vectorEffect="non-scaling-stroke"
                />
              </svg>
              {variableSelect(
                columnAxis,
                onColumnAxisChange,
                t('PRICING_MATRIX_COLUMN_AXIS'),
                'pricing-matrix__corner-columns',
              )}
              {variableSelect(
                rowAxis,
                onRowAxisChange,
                t('PRICING_MATRIX_ROW_AXIS'),
                'pricing-matrix__corner-rows',
              )}
            </th>
            {columnAxis.entries.map((column, index) => (
              <th key={column.key} className="pricing-matrix__header">
                {removeButton(
                  columnAxis,
                  onColumnAxisChange,
                  index,
                  t('PRICING_MATRIX_REMOVE_COLUMN'),
                )}
                {entryFields(columnAxis, onColumnAxisChange, column, index)}
              </th>
            ))}
            <th className="pricing-matrix__add">
              <Tooltip title={t('PRICING_MATRIX_ADD_COLUMN')}>
                <Button
                  type="dashed"
                  icon={<PlusOutlined />}
                  aria-label={t('PRICING_MATRIX_ADD_COLUMN')}
                  onClick={() => addEntry(columnAxis, onColumnAxisChange)}
                />
              </Tooltip>
            </th>
          </tr>
        </thead>
        <tbody>
          {rowAxis.entries.map((row, index) => (
            <tr key={row.key}>
              <th className="pricing-matrix__header">
                {removeButton(
                  rowAxis,
                  onRowAxisChange,
                  index,
                  t('PRICING_MATRIX_REMOVE_ROW'),
                )}
                {entryFields(rowAxis, onRowAxisChange, row, index)}
              </th>
              {columnAxis.entries.map(column => (
                <td key={column.key} className="pricing-matrix__cell">
                  <InputNumber
                    min={0}
                    step={0.5}
                    precision={2}
                    suffix="€"
                    style={{ width: '100%' }}
                    value={
                      cells[cellKey(row.key, column.key)] !== undefined
                        ? cells[cellKey(row.key, column.key)] / 100
                        : null
                    }
                    onChange={value =>
                      onCellChange(row.key, column.key, value as number)
                    }
                  />
                </td>
              ))}
              <td />
            </tr>
          ))}
          <tr>
            <th className="pricing-matrix__add">
              <Tooltip title={t('PRICING_MATRIX_ADD_ROW')}>
                <Button
                  type="dashed"
                  icon={<PlusOutlined />}
                  aria-label={t('PRICING_MATRIX_ADD_ROW')}
                  onClick={() => addEntry(rowAxis, onRowAxisChange)}
                />
              </Tooltip>
            </th>
            <td colSpan={columnAxis.entries.length + 1} />
          </tr>
        </tbody>
      </table>
    </div>
  );
};

export default PricingMatrixGrid;
