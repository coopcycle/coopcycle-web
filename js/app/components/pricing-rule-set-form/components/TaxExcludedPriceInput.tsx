import React from 'react';
import { InputNumber } from 'antd';
import { useTranslation } from 'react-i18next';

import { getCurrencySymbol } from '../../../i18n';
import { toTaxExcluded, toTaxIncluded, useTaxRate } from '../price';

type Props = {
  /** Cents with tax, which is what a pricing rule stores */
  value: number;
  /** Cents with tax, converted back from what was typed */
  onChange: (taxIncluded: number) => void;
  testId?: string;
  step?: number;
  style?: React.CSSProperties;
};

/*
  Asks for the price without tax, and stores it with tax. The two are the same
  number where the cooperative is not subject to VAT, and the hint then says
  nothing worth reading, so it is left out.
*/
const TaxExcludedPriceInput = ({
  value,
  onChange,
  testId,
  step = 0.5,
  style,
}: Props) => {
  const { t } = useTranslation();
  const taxRate = useTaxRate();

  const taxExcluded = toTaxExcluded(value, taxRate);

  return (
    <div>
      <InputNumber
        data-testid={testId}
        value={taxExcluded / 100}
        min={0}
        step={step}
        precision={2}
        suffix={`${getCurrencySymbol()} ${t('PRICING_PRICE_TAX_EXCLUDED')}`}
        style={{ width: '100%', ...style }}
        onChange={(entered: number | null) =>
          onChange(toTaxIncluded(Math.round((entered ?? 0) * 100), taxRate))
        }
      />
      {taxRate > 0 ? (
        <small className="text-muted ml-2">
          {t('PRICING_PRICE_TAX_INCLUDED', {
            amount: `${(value / 100).toFixed(2)} ${getCurrencySymbol()}`,
          })}
        </small>
      ) : null}
    </div>
  );
};

export default TaxExcludedPriceInput;
