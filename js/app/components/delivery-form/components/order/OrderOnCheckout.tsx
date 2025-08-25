import React, { useContext } from 'react';
import FlagsContext from '../../FlagsContext';
import Cart from './Cart';
import {
  HydraError,
  Order as OrderType,
  RetailPrice,
} from '../../../../api/types';
import { TotalPrice } from './TotalPrice';
import { useTranslation } from 'react-i18next';

type Props = {
  overridePrice: boolean;
  newOrder?: OrderType;
  calculatePriceData?: RetailPrice;
  calculatePriceError?: Error | HydraError;
};

export const OrderOnCheckout = ({
  overridePrice,
  newOrder,
  calculatePriceData,
  calculatePriceError,
}: Props) => {
  const { isDispatcher, isPriceBreakdownEnabled } = useContext(FlagsContext);
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
    </div>
  );
};
