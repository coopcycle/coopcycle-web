import React, { useState } from 'react';
import { Button, InputNumber, Select, Space, Steps } from 'antd';
import { useTranslation } from 'react-i18next';
import { v4 as uuidv4 } from 'uuid';

import {
  MatrixAddressSource,
  MatrixAxis,
  MatrixAxisVariable,
} from '../../../api/types';
import { AXIS_VARIABLES, VARIABLE_LABEL_KEYS, createEntries } from './axis';

type Props = {
  onDone: (rowAxis: MatrixAxis, columnAxis: MatrixAxis) => void;
  onCancel: () => void;
};

const ADDRESS_SOURCES: MatrixAddressSource[] = ['task', 'pickup', 'dropoff'];

const MAX_ENTRIES = 20;

/*
  Choosing what the grid is made of, before drawing it: the variable of each axis,
  then how many rows and columns. Everything else — labels, ranges, prices — is
  filled in on the grid itself.
*/
const PricingMatrixWizard = ({ onDone, onCancel }: Props) => {
  const { t } = useTranslation();

  const [step, setStep] = useState(0);
  const [rowVariable, setRowVariable] = useState<MatrixAxisVariable>(
    'delivery.packages.totalVolumeUnits()',
  );
  const [rowAddressSource, setRowAddressSource] =
    useState<MatrixAddressSource>('task');
  const [columnVariable, setColumnVariable] =
    useState<MatrixAxisVariable>('zone');
  const [columnAddressSource, setColumnAddressSource] =
    useState<MatrixAddressSource>('task');
  const [rowCount, setRowCount] = useState(3);
  const [columnCount, setColumnCount] = useState(3);

  const axisPicker = (
    variable: MatrixAxisVariable,
    setVariable: (variable: MatrixAxisVariable) => void,
    addressSource: MatrixAddressSource,
    setAddressSource: (source: MatrixAddressSource) => void,
  ) => (
    <Space wrap>
      <Select
        style={{ minWidth: 280 }}
        value={variable}
        onChange={setVariable}
        options={AXIS_VARIABLES.map(item => ({
          label: t(VARIABLE_LABEL_KEYS[item]),
          value: item,
        }))}
      />
      {variable === 'zone' ? (
        <Select
          style={{ minWidth: 200 }}
          value={addressSource}
          onChange={setAddressSource}
          options={ADDRESS_SOURCES.map(source => ({
            label: t(`PRICING_MATRIX_ADDRESS_SOURCE_${source}`),
            value: source,
          }))}
        />
      ) : null}
    </Space>
  );

  const preview = (
    <table className="pricing-matrix__preview">
      <tbody>
        {Array.from({ length: rowCount + 1 }, (_, rowIndex) => (
          <tr key={rowIndex}>
            {Array.from({ length: columnCount + 1 }, (_, columnIndex) => (
              <td
                key={columnIndex}
                className={
                  rowIndex === 0 || columnIndex === 0
                    ? 'pricing-matrix__preview-header'
                    : undefined
                }
              />
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );

  const steps = [
    {
      title: t('PRICING_MATRIX_WIZARD_ROWS'),
      content: axisPicker(
        rowVariable,
        setRowVariable,
        rowAddressSource,
        setRowAddressSource,
      ),
    },
    {
      title: t('PRICING_MATRIX_WIZARD_COLUMNS'),
      content: axisPicker(
        columnVariable,
        setColumnVariable,
        columnAddressSource,
        setColumnAddressSource,
      ),
    },
    {
      title: t('PRICING_MATRIX_WIZARD_SIZE'),
      content: (
        <div>
          <Space className="mb-3" wrap>
            <span>{t('PRICING_MATRIX_WIZARD_ROW_COUNT')}</span>
            <InputNumber
              min={1}
              max={MAX_ENTRIES}
              value={rowCount}
              onChange={value => setRowCount((value as number) ?? 1)}
            />
            <span>{t('PRICING_MATRIX_WIZARD_COLUMN_COUNT')}</span>
            <InputNumber
              min={1}
              max={MAX_ENTRIES}
              value={columnCount}
              onChange={value => setColumnCount((value as number) ?? 1)}
            />
          </Space>
          {preview}
        </div>
      ),
    },
  ];

  const finish = () => {
    onDone(
      {
        variable: rowVariable,
        entries: createEntries(rowCount, uuidv4),
        ...(rowVariable === 'zone' ? { addressSource: rowAddressSource } : {}),
      },
      {
        variable: columnVariable,
        entries: createEntries(columnCount, uuidv4),
        ...(columnVariable === 'zone'
          ? { addressSource: columnAddressSource }
          : {}),
      },
    );
  };

  return (
    <div data-testid="pricing-matrix-wizard">
      <Steps
        className="mb-4"
        size="small"
        current={step}
        items={steps.map(({ title }) => ({ title }))}
      />

      <div className="mb-4">{steps[step].content}</div>

      <Space>
        {step > 0 ? (
          <Button onClick={() => setStep(step - 1)}>
            {t('PRICING_MATRIX_WIZARD_BACK')}
          </Button>
        ) : null}
        {step < steps.length - 1 ? (
          <Button type="primary" onClick={() => setStep(step + 1)}>
            {t('PRICING_MATRIX_WIZARD_NEXT')}
          </Button>
        ) : (
          <Button type="primary" onClick={finish}>
            {t('PRICING_MATRIX_WIZARD_DRAW')}
          </Button>
        )}
        <Button onClick={onCancel}>{t('CANCEL')}</Button>
      </Space>
    </div>
  );
};

export default PricingMatrixWizard;
