import React from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  overridePrice: boolean;
  priceWithTaxes: number; // in cents
  priceWithoutTaxes: number; // in cents
};

export const TotalPrice = ({
  overridePrice,
  priceWithTaxes,
  priceWithoutTaxes,
}: Props) => {
  const { t } = useTranslation();

  return (
    <li
      className={`list-group-item d-flex flex-column ${overridePrice ? 'text-decoration-line-through' : ''}`}>
      <div>
        <span className="font-weight-semi-bold">
          {t('DELIVERY_FORM_TOTAL_PRICE')}
        </span>
        <span
          className={`pull-right d-flex flex-column align-items-end ${overridePrice ? 'text-decoration-line-through' : ''}`}>
          <span>
            {t('DELIVERY_FORM_TOTAL_EX_VAT')}{' '}
            {(priceWithoutTaxes / 100).formatMoney()}
          </span>
          <br />
          <span className="font-weight-semi-bold" data-testid="tax-included">
            {t('DELIVERY_FORM_TOTAL_VAT')}{' '}
            {(priceWithTaxes / 100).formatMoney()}
          </span>
        </span>
      </div>
    </li>
  );
};
