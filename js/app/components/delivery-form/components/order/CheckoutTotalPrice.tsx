import React, { useContext } from 'react'
import { useTranslation } from 'react-i18next'
import { CalculationOutput } from '../../../../api/types'
import FlagsContext from '../../FlagsContext'

const TotalPrice = ({
  overridePrice,
  priceWithTaxes,
  priceWithoutTaxes,
}: {
  overridePrice: boolean
  priceWithTaxes: number
  priceWithoutTaxes: number
}) => {
  const { t } = useTranslation()

  return (
    <span
      className={`pull-right d-flex flex-column align-items-end ${overridePrice ? 'text-decoration-line-through' : ''}`}>
      <span>
        {t('DELIVERY_FORM_TOTAL_EX_VAT')} {priceWithoutTaxes.formatMoney()}
      </span>
      <br />
      <span className="font-weight-semi-bold" data-testid="tax-included">
        {t('DELIVERY_FORM_TOTAL_VAT')} {priceWithTaxes.formatMoney()}
      </span>
    </span>
  )
}

const CheckoutTotalPrice = ({
  overridePrice,
  priceErrorMessage,
  calculatePriceData,
}: {
  overridePrice: boolean
  priceErrorMessage: string
  calculatePriceData: CalculationOutput | null
}) => {
  const { t } = useTranslation()
  const { isDispatcher } = useContext(FlagsContext)

  return (
    <>
      <li
        className={`list-group-item d-flex flex-column ${overridePrice ? 'text-decoration-line-through' : ''}`}>
        <div>
          <span className="font-weight-semi-bold">
            {t('DELIVERY_FORM_TOTAL_PRICE')}
          </span>
          {!priceErrorMessage ? (
            calculatePriceData && calculatePriceData.amount ? (
              <TotalPrice
                priceWithTaxes={calculatePriceData.amount / 100}
                priceWithoutTaxes={
                  (calculatePriceData.amount - calculatePriceData.tax.amount) /
                  100
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
        </div>
      </li>
      {!overridePrice && priceErrorMessage ? (
        <div className="alert alert-danger" role="alert">
          {isDispatcher
            ? t('DELIVERY_FORM_ADMIN_PRICE_ERROR')
            : t('DELIVERY_FORM_SHOP_PRICE_ERROR')}
        </div>
      ) : null}
    </>
  )
}

export default CheckoutTotalPrice
