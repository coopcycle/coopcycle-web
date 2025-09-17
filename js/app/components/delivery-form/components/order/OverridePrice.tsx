import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Checkbox, CheckboxChangeEvent } from 'antd';
import OverridePriceForm from './OverridePriceForm';
import { useTranslation } from 'react-i18next';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { PriceValues } from '../../types';
import { useGetTaxRatesQuery } from '../../../../api/slice';

type Props = {
  overridePrice: boolean;
  setOverridePrice: (value: boolean) => void;
};

export const OverridePrice = ({ overridePrice, setOverridePrice }: Props) => {
  const { t } = useTranslation();

  const { setFieldValue } = useDeliveryFormFormikContext();

  const [newPrice, setNewPrice] = useState(0 as 0 | PriceValues);

  const { data: taxRatesData, error: taxRatesError } = useGetTaxRatesQuery();

  const taxRate = useMemo(() => {
    if (taxRatesError) {
      return null;
    }

    if (taxRatesData) {
      const taxRates = taxRatesData['hydra:member'];
      return (
        taxRates.find(tax => tax.category === 'SERVICE') ||
        taxRates.find(tax => tax.category === 'BASE_STANDARD')
      );
    }

    return null;
  }, [taxRatesData, taxRatesError]);

  const toggleOverridePrice = useCallback(
    (value: boolean) => {
      setOverridePrice(value);
      // setNewPrice(0);
    },
    [setOverridePrice],
  );

  useEffect(() => {
    if (overridePrice && newPrice.VAT > 0) {
      setFieldValue('variantIncVATPrice', Math.round(newPrice.VAT * 100));
    }

    if (!overridePrice) {
      setFieldValue('variantIncVATPrice', null);
      setFieldValue('variantName', null);
    }
  }, [newPrice, overridePrice, setFieldValue]);

  return (
    <>
      <div>
        <Checkbox
          name="delivery.override_price"
          checked={overridePrice}
          onChange={(e: CheckboxChangeEvent) => {
            e.stopPropagation();
            toggleOverridePrice(e.target.checked);
          }}>
          {t('DELIVERY_FORM_SET_MANUALLY_PRICE')}
        </Checkbox>
      </div>
      {overridePrice && (
        <OverridePriceForm setPrice={setNewPrice} taxRate={taxRate} />
      )}
    </>
  );
};
