import { useState } from 'react';
import { getPriceValue } from '../utils';
import TaxExcludedPriceInput from './TaxExcludedPriceInput';

export type FixedPriceValue = {
  value: string;
};

type Props = {
  defaultValue: FixedPriceValue;
  onChange: (value: string) => void;
};

export default ({ defaultValue, onChange }: Props) => {
  // getPriceValue returns the stored, tax included amount in euros. The rule it
  // comes from is not re-parsed while being edited, so what was typed is kept here.
  const [taxIncluded, setTaxIncluded] = useState(
    Math.round(getPriceValue(defaultValue) * 100),
  );

  return (
    <TaxExcludedPriceInput
      testId="rule-fixed-price-input"
      value={taxIncluded}
      onChange={value => {
        setTaxIncluded(value);
        onChange(`${value}`);
      }}
    />
  );
};
