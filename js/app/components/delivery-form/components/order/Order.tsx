import React, {
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
} from 'react';
import { Checkbox, CheckboxChangeEvent, Divider, Spin } from 'antd';
import { useSelector } from 'react-redux';
import _ from 'lodash';
import { useTranslation } from 'react-i18next';

import Cart from './Cart';
import FlagsContext from '../../FlagsContext';
import { CalculationOutput, Order as OrderType } from '../../../../api/types';
import { Mode, modeIn } from '../../mode';
import OrderTotalPrice from './OrderTotalPrice';
import CheckoutTotalPrice from './CheckoutTotalPrice';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import OverridePriceForm from './OverridePriceForm';
import { selectMode } from '../../redux/formSlice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import { DeliveryFormValues, PriceValues } from '../../types';
import {
  useCalculatePriceMutation,
  useGetPricingRuleSetQuery,
  useGetStoreQuery,
  useGetTaxRatesQuery,
} from '../../../../api/slice';
import { isManualSupplement } from '../../../pricing-rule-set-form/types/PricingRuleType';
import ManualSupplements from './ManualSupplements';

type Props = {
  storeNodeId: string;
  order: OrderType | null;
  setPriceLoading: (loading: boolean) => void;
};

const Order = ({
  storeNodeId,
  order: preLoadedOrder,
  setPriceLoading,
}: Props) => {
  const { isDispatcher, isDebugPricing, isPriceBreakdownEnabled } =
    useContext(FlagsContext);

  const { t } = useTranslation();

  const [order, setOrder] = useState<OrderType | null>(preLoadedOrder);

  const mode = useSelector(selectMode);
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const [overridePrice, setOverridePrice] = useState<boolean>(() => {
    if (modeIn(mode, [Mode.DELIVERY_CREATE, Mode.RECURRENCE_RULE_UPDATE])) {
      // when cloning an order that has an arbitrary price
      if (
        values.variantIncVATPrice !== undefined &&
        values.variantIncVATPrice !== null
      ) {
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  });

  // aka "old price"
  const currentPrice = useMemo(() => {
    if (mode === Mode.DELIVERY_UPDATE && order) {
      return { exVAT: +order.total - +order.taxTotal, VAT: +order.total };
    }
  }, [order, mode]);

  const [newPrice, setNewPrice] = useState(
    0 as 0 | CalculationOutput | PriceValues,
  );

  const {
    data: taxRatesData,
    isLoading: taxRatesIsLoading,
    error: taxRatesError,
  } = useGetTaxRatesQuery();

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

  const { data: storeData, isLoading: storeIsLoading } =
    useGetStoreQuery(storeNodeId);
  const { data: pricingRuleSet, isLoading: pricingRuleSetIsLoading } =
    useGetPricingRuleSetQuery(storeData?.pricingRuleSet, {
      skip: !storeData?.pricingRuleSet,
    });

  const orderManualSupplements = useMemo(() => {
    if (!pricingRuleSet) {
      return [];
    }

    return pricingRuleSet.rules.filter(
      rule => rule.target === 'DELIVERY' && isManualSupplement(rule),
    );
  }, [pricingRuleSet]);

  const [
    calculatePrice,
    {
      data: calculatePriceData,
      error: calculatePriceError,
      isLoading: calculatePriceIsLoading,
    },
  ] = useCalculatePriceMutation();

  const calculatePriceDebounced = useMemo(
    () => _.debounce(calculatePrice, 800),
    [calculatePrice],
  );

  const convertValuesToPayload = useCallback(
    (values: DeliveryFormValues) => {
      const infos = {
        store: storeNodeId,
        tasks: structuredClone(values.tasks),
        order: structuredClone(values.order),
      };
      return infos;
    },
    [storeNodeId],
  );

  const isLoading = useMemo(() => {
    return (
      taxRatesIsLoading ||
      storeIsLoading ||
      pricingRuleSetIsLoading ||
      calculatePriceIsLoading
    );
  }, [
    taxRatesIsLoading,
    storeIsLoading,
    pricingRuleSetIsLoading,
    calculatePriceIsLoading,
  ]);

  // Pass loading state to parent component
  useEffect(() => {
    setPriceLoading(isLoading);
  }, [isLoading, setPriceLoading]);

  const calculateResponseData = useMemo(() => {
    const data = calculatePriceData;
    const error = calculatePriceError;

    if (error) {
      return error.data;
    }

    if (data) {
      return data;
    }

    return null;
  }, [calculatePriceData, calculatePriceError]);

  const priceErrorMessage = useMemo(() => {
    const error = calculatePriceError;

    if (error) {
      return error.data['hydra:description'];
    }

    return '';
  }, [calculatePriceError]);

  const toggleOverridePrice = useCallback(
    (value: boolean) => {
      setOverridePrice(value);
      setNewPrice(0);
    },
    [setOverridePrice],
  );

  useEffect(() => {
    const data = calculatePriceData;
    const error = calculatePriceError;

    if (error) {
      setNewPrice(0);
    }

    if (data) {
      setNewPrice(data);
      setOrder(data.order);
    }
  }, [calculatePriceData, calculatePriceError, setOrder]);

  useEffect(() => {
    if (mode === Mode.DELIVERY_UPDATE) {
      return;
    }

    if (overridePrice) {
      return;
    }

    // Don't calculate price until all tasks have an address
    if (!values.tasks.every(task => task.address.streetAddress)) {
      return;
    }

    // Don't calculate price if a time slot (timeSlotUrl) is selected, but no choice (timeSlot) is made yet
    if (
      !values.tasks.every(
        task => (task.timeSlotUrl && task.timeSlot) || !task.timeSlotUrl,
      )
    ) {
      return;
    }

    const infos = convertValuesToPayload(values);
    infos.tasks.forEach(task => {
      if (task['@id']) {
        delete task['@id'];
      }
    });

    calculatePriceDebounced(infos);
  }, [
    mode,
    overridePrice,
    values,
    convertValuesToPayload,
    calculatePriceDebounced,
  ]);

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
    <Spin spinning={isLoading}>
      <div>
        {isPriceBreakdownEnabled && order !== null ? (
          <Cart order={order} overridePrice={overridePrice} />
        ) : null}
        <div>
          {mode === Mode.DELIVERY_UPDATE ? (
            <OrderTotalPrice
              overridePrice={overridePrice}
              currentPrice={currentPrice}
              newPrice={newPrice}
            />
          ) : (
            <CheckoutTotalPrice
              overridePrice={overridePrice}
              priceErrorMessage={priceErrorMessage}
              calculatePriceData={calculatePriceData}
            />
          )}

          {!overridePrice &&
            (isDispatcher || isDebugPricing) &&
            Boolean(calculateResponseData) && (
              <PriceCalculation
                className="mt-2"
                isDebugPricing={isDebugPricing}
                calculation={calculateResponseData.calculation}
                order={calculateResponseData.order}
              />
            )}

          {isDispatcher &&
          !overridePrice &&
          mode !== Mode.DELIVERY_UPDATE &&
          orderManualSupplements.length > 0 ? (
            <div>
              <Divider size="middle" />
              <ManualSupplements rules={orderManualSupplements} />
            </div>
          ) : null}

          {isDispatcher && (
            <div>
              <Divider size="middle" />
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
            </div>
          )}
        </div>
      </div>
    </Spin>
  );
};

export default Order;
