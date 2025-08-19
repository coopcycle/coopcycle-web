import React, { useContext } from 'react';
import { useTranslation } from 'react-i18next';
import { RetailPrice } from '../../../../api/types';
import FlagsContext from '../../FlagsContext';
import { TotalPrice } from './TotalPrice';

type Props = {
  overridePrice: boolean;
  priceErrorMessage: string;
  calculatePriceData?: RetailPrice;
};

const CheckoutTotalPrice = ({
  overridePrice,
  priceErrorMessage,
  calculatePriceData,
}: Props) => {
  const { t } = useTranslation();
  const { isDispatcher } = useContext(FlagsContext);

  return (
    <>
      {!priceErrorMessage ? (
        calculatePriceData && calculatePriceData.amount ? (
          <TotalPrice
            priceWithTaxes={calculatePriceData.amount}
            priceWithoutTaxes={
              calculatePriceData.amount - calculatePriceData.tax.amount
            }
            overridePrice={overridePrice}
          />
        ) : (
          <TotalPrice
            priceWithTaxes={0}
            priceWithoutTaxes={0}
            overridePrice={overridePrice}
          />
        )
      ) : null}
      {!overridePrice && priceErrorMessage ? (
        <div className="alert alert-danger" role="alert">
          {isDispatcher
            ? t('DELIVERY_FORM_ADMIN_PRICE_ERROR')
            : t('DELIVERY_FORM_SHOP_PRICE_ERROR')}
        </div>
      ) : null}
    </>
  );
};

export default CheckoutTotalPrice;
