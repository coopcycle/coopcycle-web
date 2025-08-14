import React, { useState, useRef, useEffect } from 'react';
import { getCurrencySymbol } from '../../../i18n';
import { useTranslation } from 'react-i18next';

type Unit = 'km' | 'kg' | 'vu';

type UnitLabelProps = {
  unit: Unit;
};

const UnitLabel = ({ unit }: UnitLabelProps) => {
  const { t } = useTranslation();

  if (unit === 'vu') {
    return <span>{t('RULE_PICKER_LINE_VOLUME_UNITS')}</span>;
  }

  return <span>{unit}</span>;
};

const unitToAttribute = (unit: Unit): string => {
  switch (unit) {
    case 'km':
      return 'distance';
    case 'kg':
      return 'weight';
    case 'vu':
      return 'packages.totalVolumeUnits()';
  }
};

const attributeToUnit = (attribute: string): Unit => {
  switch (attribute) {
    case 'distance':
      return 'km';
    case 'weight':
      return 'kg';
    case 'packages.totalVolumeUnits()':
      return 'vu';
    default:
      return 'km';
  }
};

const multiplyIfNeeded = (value: number, unit: Unit): number => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value * 1000;
  }

  return value;
};

const divideIfNeeded = (value: number, unit: Unit): number => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value / 1000;
  }

  return value;
};

export type PriceRangeValue = {
  attribute: string;
  price: number;
  step: number;
  threshold: number;
};

type Props = {
  defaultValue: PriceRangeValue;
  onChange: (value: PriceRangeValue) => void;
};

export default ({ defaultValue, onChange }: Props) => {
  const { t } = useTranslation();

  const defaultAttribute = defaultValue.attribute || 'distance';

  const [unit, setUnit] = useState(attributeToUnit(defaultAttribute));

  const [attribute, setAttribute] = useState(defaultAttribute);
  const [price, setPrice] = useState(defaultValue.price || 0);
  const [step, setStep] = useState(defaultValue.step || 1000);
  const [threshold, setThreshold] = useState(defaultValue.threshold || 0);

  const stepEl = useRef(null);
  const thresholdEl = useRef(null);
  const initialLoad = useRef(true);

  useEffect(() => {
    if (!initialLoad.current) {
      onChange({
        attribute,
        price: price,
        step,
        threshold,
      });
    } else {
      initialLoad.current = false;
    }
  }, [price, threshold, attribute, step]);

  return (
    <div data-testid="price_rule_price_range_editor">
      <label className="mr-2">
        <input
          data-testid="rule-price-range-price"
          type="number"
          size="4"
          defaultValue={price / 100}
          min="0"
          step=".001"
          className="form-control d-inline-block no-number-input-arrow"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setPrice(parseFloat(e.target.value) * 100);
          }}
        />
        <span className="ml-2">{getCurrencySymbol()}</span>
      </label>
      <label>
        <span className="mx-2">{t('PRICE_RANGE_EDITOR.FOR_EVERY')}</span>
        <input
          data-testid="rule-price-range-step"
          type="number"
          size="4"
          min="0.1"
          step=".1"
          defaultValue={divideIfNeeded(step, unit)}
          ref={stepEl}
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setStep(multiplyIfNeeded(parseFloat(e.target.value), unit));
          }}
        />
        <select
          data-testid="rule-price-range-unit"
          className="form-control d-inline-block align-top ml-2"
          style={{ width: '70px' }}
          defaultValue={attributeToUnit(attribute)}
          onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
            setAttribute(unitToAttribute(e.target.value as Unit));

            const newUnit = e.target.value as Unit;
            const prevUnit = unit;

            if (newUnit === 'vu' && prevUnit !== 'vu') {
              setStep(step / 1000);
              setThreshold(threshold / 1000);
            } else if (newUnit !== 'vu' && prevUnit === 'vu') {
              setStep(step * 1000);
              setThreshold(threshold * 1000);
            }

            setUnit(e.target.value as Unit);
          }}>
          <option value="km">{t('PRICING_RULE_PICKER_UNIT_KM')}</option>
          <option value="kg">{t('PRICING_RULE_PICKER_UNIT_KG')}</option>
          <option value="vu">{t('RULE_PICKER_LINE_VOLUME_UNITS')}</option>
        </select>
      </label>
      <label>
        <span className="mx-2">{t('PRICE_RANGE_EDITOR.ABOVE')}</span>
        <input
          data-testid="rule-price-range-threshold"
          type="number"
          size="4"
          min="0"
          step=".1"
          defaultValue={divideIfNeeded(threshold, unit)}
          ref={thresholdEl}
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setThreshold(multiplyIfNeeded(parseFloat(e.target.value), unit));
          }}
        />
        <span className="ml-2">
          <UnitLabel unit={unit} />
        </span>
      </label>
    </div>
  );
};
