import React from 'react'
import { PriceValues } from '../../types'
import { useTranslation } from 'react-i18next'
import { money } from '../../../../../../assets/react/controllers/Incident/utils'

const OrderTotalPrice = ({
  overridePrice,
  currentPrice,
  newPrice,
}: {
  overridePrice: boolean
  currentPrice: PriceValues
  newPrice: PriceValues
}) => {
  const { t } = useTranslation()

  return (
    <>
      <div className="font-weight-semi-bold mb-1 total__price">
        {overridePrice
          ? t('DELIVERY_FORM_OLD_PRICE')
          : t('DELIVERY_FORM_TOTAL_PRICE')}
      </div>
      <div>
        {overridePrice ? (
          <s>
            {money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
          </s>
        ) : (
          <span>
            {money(currentPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
          </span>
        )}
      </div>
      <div>
        {overridePrice ? (
          <s data-testid="tax-included-previous">
            {money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
          </s>
        ) : (
          <span data-testid="tax-included-previous">
            {money(currentPrice.VAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
          </span>
        )}
      </div>
      {overridePrice && (
        <div className="mb-1">
          <div className="font-weight-bold mb-1 total__price">
            {t('DELIVERY_FORM_NEW_PRICE')}
          </div>

          <span>
            {money(newPrice.exVAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
          </span>
          <br />
          <span data-testid="tax-included">
            {money(newPrice.VAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_VAT')}
          </span>
        </div>
      )}
    </>
  )
}

export default OrderTotalPrice
