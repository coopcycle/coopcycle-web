import { useState } from 'react';
import { InputNumber } from 'antd';
import { getPriceValue } from '../utils';

export type FixedPriceValue = {
  value: string;
};

type Props = {
  defaultValue: FixedPriceValue;
  onChange: (value: string) => void;
};

export default ({ defaultValue, onChange }: Props) => {
  const [value, setValue] = useState(getPriceValue(defaultValue));

  //TODO: fix currency?
  return (
    <InputNumber
      data-testid="rule-fixed-price-input"
      value={value}
      onChange={(value: number | null) => {
        if (!value) {
          return;
        }

        setValue(value);
        onChange(`${(value || 0) * 100}`);
      }}
      style={{ width: '100%' }}
      step={0.5}
      min={0}
      precision={2}
      addonAfter="€"
    />
  );
};
