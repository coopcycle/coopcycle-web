import React, { useContext } from 'react';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import {
  HydraError,
  Order as OrderType,
  PricingRule,
  RetailPrice,
} from '../../../../api/types';
import { TotalPrice } from './TotalPrice';
import { useTranslation } from 'react-i18next';
import { PriceCalculation } from '../../../../delivery/PriceCalculation';
import { Divider } from 'antd';
import ManualSupplements from './ManualSupplements';

type Props = {
  orderManualSupplements?: PricingRule[];
  overridePrice: boolean;
  newOrder?: OrderType;
  calculatePriceData?: RetailPrice;
  calculatePriceError?: Error | HydraError;
};

export const OrderOnCheckout = ({
  orderManualSupplements = [],
  overridePrice,
  newOrder,
  calculatePriceData,
  calculatePriceError,
}: Props) => {
  const { isDispatcher, isPriceBreakdownEnabled, isDebugPricing } =
    useContext(FlagsContext);
  const { t } = useTranslation();

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
        calculatePriceData && (
          <PriceCalculation
            className="mt-2"
            isDebugPricing={isDebugPricing}
            calculation={calculatePriceData.calculation}
            order={calculatePriceData.order}
          />
        )}

      {isDispatcher && !overridePrice && orderManualSupplements.length > 0 ? (
        <div>
          <Divider size="middle" />
          <ManualSupplements rules={orderManualSupplements} />
        </div>
      ) : null}
    </div>
  );
};
