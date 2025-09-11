import React from 'react';
import { Button, Input } from 'antd';

type Props = {
  value: number;
  onChange: (value: number) => void;
  min?: number;
  max?: number;
  step?: number;
}

export default function RangeInput({
  value,
  onChange,
  min = 0,
  max = 999,
  step = 1
}: Props) {
  const handleDecrease = () => {
    const newValue = Math.max(min, value - step);
    onChange(newValue);
  };

  const handleIncrease = () => {
    const newValue = Math.min(max, value + step);
    onChange(newValue);
  };

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = Number(e.target.value);
    if (!isNaN(newValue) && newValue >= min && newValue <= max) {
      onChange(newValue);
    }
  };

  return (
    <div className="range-input d-flex align-items-center">
      <Button
        size="small"
        onClick={handleDecrease}
        disabled={value <= min}
        data-testid="range-input-decrease"
      >
        -
      </Button>
      <Input
        type="number"
        value={value}
        onChange={handleInputChange}
        min={min}
        max={max}
        step={step}
        style={{ width: '80px', margin: '0 8px' }}
        data-testid="range-input-field"
      />
      <Button
        size="small"
        onClick={handleIncrease}
        disabled={value >= max}
        data-testid="range-input-increase"
      >
        +
      </Button>
    </div>
  );
}
