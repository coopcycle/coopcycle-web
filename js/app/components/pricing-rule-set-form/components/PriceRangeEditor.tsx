import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import TaxExcludedPriceInput from './TaxExcludedPriceInput';

type Attribute =
  | 'distance'
  | 'weight'
  | 'packages.totalVolumeUnits()'
  | 'quantity';

type Unit = 'km' | 'kg' | 'vu' | 'item';

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

const unitToAttribute = (unit: Unit): Attribute => {
  switch (unit) {
    case 'km':
      return 'distance';
    case 'kg':
      return 'weight';
    case 'vu':
      return 'packages.totalVolumeUnits()';
    case 'item':
      return 'quantity';
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
    case 'quantity':
      return 'item';
    default:
      return 'km';
  }
};

const parseValueFromUi = (value: number, unit: Unit): number => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value * 1000;
  }

  return value;
};

const defaultStepValue = (unit: Unit): number => {
  switch (unit) {
    case 'km':
    case 'kg':
      return 100; // 100m/100g
  }

  return 1;
};

const formatValueForUi = (value: number, unit: Unit): number => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value / 1000;
  }

  return value;
};

/*
  The multiplier charges the range once per unit of something else: with the volume
  units of the delivery, "0.50 € per km beyond 2.5 km, per package" is a single rule
  rather than one rule per package count.
*/
export type Multiplier =
  | 'packages.totalVolumeUnits()'
  | 'delivery.packages.totalVolumeUnits()';

export type PriceRangeValue = {
  attribute: Attribute;
  price: number;
  step: number;
  threshold: number;
  multiplier?: Multiplier | null;
};

type Props = {
  isManualSupplement: boolean;
  defaultValue: PriceRangeValue;
  onChange: (value: PriceRangeValue) => void;
};

export default ({ isManualSupplement, defaultValue, onChange }: Props) => {
  const { t } = useTranslation();

  const defaultAttribute =
    defaultValue.attribute || (isManualSupplement ? 'quantity' : 'distance');

  const [unit, setUnit] = useState(attributeToUnit(defaultAttribute));

  const [attribute, setAttribute] = useState(defaultAttribute);
  const [price, setPrice] = useState(defaultValue.price || 0);
  const [step, setStep] = useState(
    defaultValue.step || (isManualSupplement ? 1 : 1000),
  );
  const [threshold, setThreshold] = useState(defaultValue.threshold || 0);
  const [multiplier, setMultiplier] = useState<Multiplier | null>(
    defaultValue.multiplier ?? null,
  );

  const initialLoad = useRef(true);

  useEffect(() => {
    if (!initialLoad.current) {
      onChange({
        attribute,
        price: price,
        step,
        threshold,
        multiplier,
      });
    } else {
      initialLoad.current = false;
    }
  }, [price, threshold, attribute, step, multiplier, onChange]);

  return (
    <div data-testid="price_rule_price_range_editor">
      <label className="mr-2 align-top">
        <TaxExcludedPriceInput
          testId="rule-price-range-price"
          value={price}
          step={0.1}
          style={{ width: '150px' }}
          onChange={setPrice}
        />
      </label>
      <label>
        <span className="mx-2">{t('PRICE_RANGE_EDITOR.FOR_EVERY')}</span>
        <input
          data-testid="rule-price-range-step"
          type="number"
          size={4}
          min={formatValueForUi(defaultStepValue(unit), unit)}
          step={formatValueForUi(defaultStepValue(unit), unit)}
          defaultValue={formatValueForUi(step, unit)}
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setStep(parseValueFromUi(parseFloat(e.target.value), unit));
          }}
        />
        {!isManualSupplement ? (
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
        ) : null}
      </label>
      <label>
        <span className="mx-2">{t('PRICE_RANGE_EDITOR.ABOVE')}</span>
        <input
          data-testid="rule-price-range-threshold"
          type="number"
          size={4}
          min="0"
          step={formatValueForUi(defaultStepValue(unit), unit)}
          defaultValue={formatValueForUi(threshold, unit)}
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setThreshold(parseValueFromUi(parseFloat(e.target.value), unit));
          }}
        />
        {!isManualSupplement ? (
          <span className="ml-2">
            <UnitLabel unit={unit} />
          </span>
        ) : null}
      </label>
      {/*
        On a row of its own, and only once asked for: without it the range is
        charged once, which is what most rules want and what every rule stored
        before the multiplier existed does.
      */}
      {!isManualSupplement && multiplier ? (
        <div className="mt-2">
          <label className="mr-2">
            <span className="mr-2">{t('PRICE_RANGE_EDITOR.MULTIPLIER')}</span>
            <select
              data-testid="rule-price-range-multiplier"
              className="form-control d-inline-block"
              style={{ width: '260px' }}
              value={multiplier}
              onChange={(e: React.ChangeEvent<HTMLSelectElement>) => {
                setMultiplier(e.target.value as Multiplier);
              }}>
              <option value="packages.totalVolumeUnits()">
                {t('PRICE_RANGE_EDITOR.PER_VOLUME_UNIT')}
              </option>
              <option value="delivery.packages.totalVolumeUnits()">
                {t('PRICE_RANGE_EDITOR.PER_VOLUME_UNIT_DELIVERY')}
              </option>
            </select>
          </label>
          <button
            type="button"
            className="btn btn-xs btn-default"
            onClick={() => setMultiplier(null)}>
            <i className="fa fa-times mr-1"></i>
            <span>{t('PRICE_RANGE_EDITOR.DEL_MULTIPLIER')}</span>
          </button>
        </div>
      ) : null}
      {!isManualSupplement && !multiplier ? (
        <div className="mt-2">
          <button
            type="button"
            className="btn btn-xs btn-default"
            onClick={() => setMultiplier('packages.totalVolumeUnits()')}>
            <i className="fa fa-plus mr-1"></i>
            <span>{t('PRICE_RANGE_EDITOR.ADD_MULTIPLIER')}</span>
          </button>
        </div>
      ) : null}
    </div>
  );
};
