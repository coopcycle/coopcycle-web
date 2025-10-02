import React, { useState, useRef, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import HelpIcon from '../../../components/HelpIcon';

const convertToUi = (value: number): number => {
  return value / 100 - 100;
};

const convertFromUi = (value: string): number => {
  return Math.round((parseFloat(value) + 100) * 100);
};

export type PercentagePriceValue = {
  percentage: number;
};

type Props = {
  defaultValue: PercentagePriceValue;
  onChange: (value: { percentage: number }) => void;
};

export default ({ defaultValue, onChange }: Props) => {
  const [percentage, setPercentage] = useState(
    defaultValue.percentage || 10000,
  ); // 10000 = 100.00%

  const { t } = useTranslation();

  const initialLoad = useRef(true);

  useEffect(() => {
    if (!initialLoad.current) {
      onChange({
        percentage,
      });
    } else {
      initialLoad.current = false;
    }
  }, [percentage, onChange]);

  return (
    <div>
      <label className="mr-2">
        <input
          data-testid="rule-percentage-input"
          type="number"
          size="4"
          defaultValue={convertToUi(percentage)}
          step="0.01"
          className="form-control d-inline-block no-number-input-arrow"
          style={{ width: '80px' }}
          onChange={(e: React.ChangeEvent<HTMLInputElement>) => {
            setPercentage(convertFromUi(e.target.value));
          }}
        />
        <span className="ml-2">%</span>
        <HelpIcon
          className="ml-2"
          tooltipText={t('PRICE_RANGE_EDITOR.TYPE_PERCENTAGE_HELP')}
        />
      </label>
    </div>
  );
};
