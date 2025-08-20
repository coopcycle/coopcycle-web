import React from 'react';
import { useTranslation } from 'react-i18next';

type Props = {
  overridePrice: boolean;
  total: number; // in cents with tax
  taxTotal: number; // in cents
};

export const TotalPrice = ({ overridePrice, total, taxTotal }: Props) => {
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
            {((total - taxTotal) / 100).formatMoney()}
          </span>
          <br />
          <span
            className="font-weight-semi-bold"
            data-testid={
              overridePrice ? 'tax-included-previous' : 'tax-included'
            }>
            {t('DELIVERY_FORM_TOTAL_VAT')} {(total / 100).formatMoney()}
          </span>
        </span>
      </div>
    </li>
  );
};
