import React, { useContext } from 'react';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import {
  CalculationOutput,
  HydraError,
  Order as OrderType,
  PricingRule,
  RetailPrice,
  Uri,
} from '../../../../api/types';
import { TotalPrice } from './TotalPrice';
import { useTranslation } from 'react-i18next';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import { Checkbox, Divider } from 'antd';
import ManualSupplements from './ManualSupplements';
import { UserContext } from '../../../../UserContext';
import { useGetStorePaymentMethodsQuery } from '../../../../api/slice';
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext';
import CashOnDeliveryDisclaimer from './CashOnDeliveryDisclaimer';
import BlockLabel from '../BlockLabel';

type Props = {
  storeNodeId: Uri;
  orderManualSupplements?: PricingRule[];
  overridePrice: boolean;
  newOrder?: OrderType;
  calculatePriceData?: RetailPrice;
  calculatePriceError?: Error | HydraError;
  priceCalculation?: { calculation: CalculationOutput; order?: OrderType };
};

export const OrderOnCheckout = ({
  storeNodeId,
  orderManualSupplements = [],
  overridePrice,
  newOrder,
  calculatePriceData,
  calculatePriceError,
  priceCalculation,
}: Props) => {
  const { isDispatcher } = useContext(UserContext);
  const { isPriceBreakdownEnabled, isDebugPricing } = useContext(FlagsContext);
  const { t } = useTranslation();
  const { values, setFieldValue } = useDeliveryFormFormikContext();

  const { data: paymentMethods } = useGetStorePaymentMethodsQuery(storeNodeId);

  const isCashOnDeliveryAvailable =
    paymentMethods?.methods?.some(
      method => method.type === 'cash_on_delivery',
    ) ?? false;

  return (
    <div>
      {isPriceBreakdownEnabled && newOrder ? (
        <Cart orderItems={newOrder.items} overridePrice={overridePrice} />
      ) : null}
      {!calculatePriceError ? (
        calculatePriceData && calculatePriceData.amount ? (
          <TotalPrice
            total={calculatePriceData.amount}
            taxTotal={calculatePriceData.tax.amount}
            overridePrice={overridePrice}
          />
        ) : (
          <TotalPrice total={0} taxTotal={0} overridePrice={overridePrice} />
        )
      ) : null}
      {!overridePrice && calculatePriceError ? (
        <div className="alert alert-danger" role="alert">
          {isDispatcher
            ? t('DELIVERY_FORM_ADMIN_PRICE_ERROR')
            : t('DELIVERY_FORM_SHOP_PRICE_ERROR')}
        </div>
      ) : null}
      {!overridePrice &&
        (isDispatcher || isDebugPricing) &&
        priceCalculation && (
          <PriceCalculation
            className="mt-2"
            isDebugPricing={isDebugPricing}
            calculation={priceCalculation.calculation}
            order={priceCalculation.order}
          />
        )}

      {isDispatcher && !overridePrice && orderManualSupplements.length > 0 ? (
        <div>
          <Divider size="middle" />
          <ManualSupplements rules={orderManualSupplements} />
        </div>
      ) : null}

      {isCashOnDeliveryAvailable ? (
        <div>
          <Divider size="middle" />
          <BlockLabel label={t('PAYMENT_FORM_TITLE')} />
          <Checkbox
            checked={values.order.paymentMethod === 'cash_on_delivery'}
            data-testid="cash-on-delivery-checkbox"
            onChange={e => {
              setFieldValue(
                'order.paymentMethod',
                e.target.checked ? 'cash_on_delivery' : undefined,
              );
            }}>
            {t('PM_CASH')}
          </Checkbox>
          {values.order.paymentMethod === 'cash_on_delivery' && (
            <CashOnDeliveryDisclaimer />
          )}
        </div>
      ) : null}
    </div>
  );
};
