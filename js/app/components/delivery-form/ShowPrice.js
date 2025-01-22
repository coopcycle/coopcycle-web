import React from 'react'
import { money } from '../../../../assets/react/controllers/Incident/utils'
import OverridePrice from './OverridePrice'
import { useTranslation } from 'react-i18next'
import { useFormikContext } from 'formik'

export default ({
  deliveryId,
  deliveryPrice,
  calculatedPrice,
  priceError,
  setOverridePrice,
  overridePrice,
  setCalculatePrice,
}) => {
  const { t } = useTranslation()

  const { values } = useFormikContext()

  console.log(calculatedPrice)
  if (values.variantPrice) console.log('variant Pricr', values.variantPrice)

  return (
    <>
      {deliveryPrice ? (
        <div className="mb-4">
          <div className="font-weight-bold mb-2">
            {t('DELIVERY_FORM_OLD_PRICE')}
          </div>
          <div>
            {money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
          </div>
          <div>
            {money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
          </div>
          <div className="mt-2 small">
            Editing price is not already available in beta version.
          </div>
        </div>
      ) : null}

      {!deliveryId ? (
        <>
          <div className="font-weight-bold mb-2">
            {deliveryId
              ? t('DELIVERY_FORM_NEW_PRICE')
              : t('DELIVERY_FORM_TOTAL_PRICE')}{' '}
          </div>
          <div>
            {calculatedPrice.amount ? (
              <div>
                <div className="mb-1">
                  {money(calculatedPrice.amount)} {t('DELIVERY_FORM_TOTAL_VAT')}
                </div>
                {!overridePrice && (
                  <div>
                    {money(calculatedPrice.amount - calculatedPrice.tax.amount)}{' '}
                    {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                  </div>
                )}
              </div>
            ) : (
              <div>
                <div className="mb-1">
                  {money(0)} {t('DELIVERY_FORM_TOTAL_VAT')}
                </div>
                <div>
                  {money(0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                </div>
              </div>
            )}
            <OverridePrice
              deliveryId={deliveryId}
              setOverridePrice={setOverridePrice}
              overridePrice={overridePrice}
              setCalculatePrice={setCalculatePrice}
            />
          </div>
          {priceError.isPriceError ? (
            <div className="alert alert-danger mt-4" role="alert">
              {priceError.priceErrorMessage}
            </div>
          ) : null}
        </>
      ) : null}
    </>
  )
}
