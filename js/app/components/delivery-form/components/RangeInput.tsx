import React from 'react';
import { Button, InputNumber } from 'antd';

type Props = {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  step?: number;
};

export default function RangeInput({
  value,
  onChange,
  min = 0,
  max = 999,
  step = 1,
}: Props) {
  const handleDecrease = () => {
    const newValue = Math.max(min, value - step);
    onChange(newValue);
  };

  const handleIncrease = () => {
    const newValue = Math.min(max, value + step);
    onChange(newValue);
  };

  const handleInputChange = (value: number | null) => {
    const newValue = Number(value);
    if (!isNaN(newValue) && newValue >= min && newValue <= max) {
      onChange(newValue);
    }
  };

  return (
    <div className="range-input d-flex align-items-center">
      <InputNumber
        style={{ width: '160px' }}
        data-testid="range-input-field"
        addonBefore={
          <Button
            type="text"
            size="small"
            onClick={handleDecrease}
            disabled={value <= min}
            data-testid="range-input-decrease">
            -
          </Button>
        }
        addonAfter={
          <Button
            type="text"
            size="small"
            onClick={handleIncrease}
            disabled={value >= max}
            data-testid="range-input-increase">
            +
          </Button>
        }
        value={value}
        onChange={handleInputChange}
        min={min}
        max={max}
        step={step}
        defaultValue={100}
      />
    </div>
  );
}
