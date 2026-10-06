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
  // getPriceValue returns the stored, tax included amount in euros
  const taxIncluded = Math.round(getPriceValue(defaultValue) * 100);

  return (
    <TaxExcludedPriceInput
      testId="rule-fixed-price-input"
      value={taxIncluded}
      onChange={value => onChange(`${value}`)}
    />
  );
};
