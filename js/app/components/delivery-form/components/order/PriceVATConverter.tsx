import React, { useState } from 'react';
import { InputNumber } from 'antd';
import { useTranslation } from 'react-i18next';
import './PriceVATConverter.scss';
import { PriceValues } from '../../types';

type MissingPriceValues = {
  VAT: number | null;
  exVAT: number | null;
};

const getCurrencySymbol = (): string => {
  const { currencySymbol } = document.body.dataset;
  return currencySymbol || '€';
};

const addVat = (vatExcludedPrice: number, taxRate: number): number => {
  return Math.round(vatExcludedPrice * 100 * (taxRate + 1)) / 100;
};

const removeVat = (vatIncludedPrice: number, taxRate: number): number => {
  return Math.round((vatIncludedPrice * 100) / (taxRate + 1)) / 100;
};

type Props = {
  taxRate: number;
  setPrice: (price: PriceValues) => void;
  VAT?: number;
  exVAT?: number;
};

const PriceVATConverter = ({ taxRate, setPrice, VAT, exVAT }: Props) => {
  const [values, setValues] = useState<PriceValues | MissingPriceValues>(() => {
    if (VAT === undefined && exVAT === undefined) {
      return { VAT: null, exVAT: null };
    } else if (VAT !== undefined && exVAT !== undefined) {
      return { VAT: VAT, exVAT: exVAT };
    } else if (VAT !== undefined) {
      return { VAT: VAT, exVAT: removeVat(VAT, taxRate) };
    } else if (exVAT !== undefined) {
      return { VAT: addVat(exVAT, taxRate), exVAT: exVAT };
    }
    return { VAT: null, exVAT: null };
  });

  const { t } = useTranslation();

  return (
    <div className="row">
      <div className="col-xs-6 field">
        <label
          className="variant-price-exVAT___label font-weight-bold mr-3"
          htmlFor="variantPriceExVAT">
          {t('DELIVERY_FORM_EXVAT_PRICE')}
        </label>

        <InputNumber
          id="variantPriceExVAT"
          controls={false}
          prefix={getCurrencySymbol()}
          value={values.exVAT}
          placeholder={'0'}
          onChange={value => {
            if (value === null) {
              return;
            }

            const newValues = {
              exVAT: value,
              VAT: addVat(value, taxRate),
            };
            setValues(newValues);
            setPrice(newValues);
          }}
        />
      </div>

      <div className="col-xs-6 field">
        <label
          className="variant-price-VAT___label font-weight-bold mr-3"
          htmlFor="variantPriceVAT">
          {t('DELIVERY_FORM_VAT_PRICE')}
        </label>

        <InputNumber
          id="variantPriceVAT"
          controls={false}
          prefix={getCurrencySymbol()}
          value={values.VAT}
          placeholder={'0'}
          onChange={(value: number | null) => {
            if (value === null) {
              return;
            }

            const newValues = {
              exVAT: removeVat(value, taxRate),
              VAT: value,
            };
            setValues(newValues);
            setPrice(newValues);
          }}
        />
      </div>
    </div>
  );
};

export default PriceVATConverter;
