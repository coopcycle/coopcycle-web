import React, { useState, useEffect } from 'react'
import { money } from '../../../../assets/react/controllers/Incident/utils'
import { Checkbox, Input } from 'antd'
import { useFormikContext, Field } from 'formik'
import { useTranslation } from 'react-i18next'
import PriceVATConverter from './PriceVATConverter'
import Spinner from '../core/Spinner'
import './ShowPrice.scss'

const baseURL = location.protocol + '//' + location.host

const OverridePriceForm = ({ setCalculatePrice, taxRate }) => {
  const { errors, setFieldValue } = useFormikContext()

  const { t } = useTranslation()

  return (
    <div className="override__form p-2 mb-3">
      <div className="override__form__variant-name">
        <label
          className="override__form__variant-name___label font-weight-bold"
          htmlFor="variantName">
          {t('DELIVERY_FORM_PRODUCT_NAME')}
        </label>
        <div className="override__form__variant-name___input">
          <Field name={'variantName'}>
            {({ field }) => (
              <Input
                id="variantName"
                {...field}
                onChange={e => {
                  setFieldValue('variantName', e.target.value)
                }}
              />
            )}
          </Field>
          {errors.variantName && (
            <div className="text-danger">{errors.variantName}</div>
          )}
          <div className="small text-muted">
            {t('DELIVERY_FORM_NAME_INSTRUCTION')}
          </div>
        </div>
      </div>
      <PriceVATConverter
        className="override__form__variant-price"
        amount={taxRate.amount}
        setPrices={setCalculatePrice}
      />
    </div>
  )
}

export default ({
  deliveryId,
  deliveryPrice,
  calculatedPrice,
  priceError,
  setOverridePrice,
  overridePrice,
  setCalculatePrice,
  isAdmin,
  priceLoading,
}) => {
  const { t } = useTranslation()
  const { setFieldValue } = useFormikContext()

  const [taxRate, setTaxRate] = useState(null)

  useEffect(() => {
    if (overridePrice && calculatedPrice.VAT > 0) {
      setFieldValue(
        'variantIncVATPrice',
        (calculatedPrice.VAT * 100).toFixed(2),
      )
    }

    if (!overridePrice) {
      setFieldValue('variantIncVATPrice', null)
      setFieldValue('variantName', null)
    }
  }, [calculatedPrice, overridePrice])

  const httpClient = new window._auth.httpClient()

  useEffect(() => {
    const getDeliveryTaxs = async () => {
      const { response, error } = await httpClient.get(
        `${baseURL}/api/tax_rates`,
      )

      if (error) {
        return
      }

      if (response) {
        const taxRates = await response['hydra:member']
        setTaxRate(
          taxRates.find(tax => tax.category === 'SERVICE') ||
            taxRates.find(tax => tax.category === 'BASE_STANDARD'),
        )
      }
    }
    getDeliveryTaxs()
  }, [])

  return (
    <>
      {deliveryPrice ? (
        <>
          {overridePrice && (
            <OverridePriceForm
              setCalculatePrice={setCalculatePrice}
              taxRate={taxRate}
            />
          )}

          <div className="mb-3 pl-2">
            <div className="font-weight-bold mb-2 total__price">
              {!overridePrice
                ? t('DELIVERY_FORM_TOTAL_PRICE')
                : t('DELIVERY_FORM_OLD_PRICE')}
            </div>
            <div>
              {money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
            </div>
            <div>
              {money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}
            </div>

            {overridePrice && (
              <div className="mt-3 mb-3">
                <div className="font-weight-bold mb-2 total__price">
                  {t('DELIVERY_FORM_NEW_PRICE')}
                </div>

                <div className="mt-2">
                  {money(calculatedPrice.exVAT * 100 || 0)}{' '}
                  {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                </div>
                <div>
                  {money(calculatedPrice.VAT * 100 || 0)}{' '}
                  {t('DELIVERY_FORM_TOTAL_VAT')}
                </div>
              </div>
            )}

            {isAdmin && (
              <div style={{ maxWidth: '100%' }} className="mt-4">
                <div>
                  {t('DELIVERY_FORM_SET_MANUALLY_PRICE')}
                  <Checkbox
                    className="ml-4 mb-2"
                    checked={overridePrice}
                    onChange={e => {
                      setOverridePrice(e.target.checked)
                      setCalculatePrice(0)
                    }}></Checkbox>
                </div>
              </div>
            )}
          </div>
        </>
      ) : null}

      {!deliveryId ? (
        <>
          {overridePrice && (
            <OverridePriceForm
              setCalculatePrice={setCalculatePrice}
              taxRate={taxRate}
            />
          )}

          <div className="p-2 mb-3">
            <div className="font-weight-bold mb-3 total__price">
              {t('DELIVERY_FORM_TOTAL_PRICE')}
            </div>
            <div>
              {calculatedPrice.amount && !overridePrice ? (
                <div>
                  {priceLoading ? (
                    <Spinner />
                  ) : (
                    <>
                      <div className="mb-1">
                        {money(
                          calculatedPrice.amount - calculatedPrice.tax.amount,
                        )}{' '}
                        {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                      </div>
                      {!overridePrice && (
                        <div>
                          {money(calculatedPrice.amount)}{' '}
                          {t('DELIVERY_FORM_TOTAL_VAT')}
                        </div>
                      )}
                    </>
                  )}
                </div>
              ) : overridePrice ? (
                <div>
                  <div className="mt-2 mb-1">
                    {money(calculatedPrice.exVAT * 100 || 0)}{' '}
                    {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                  </div>
                  <div>
                    {money(calculatedPrice.VAT * 100 || 0)}{' '}
                    {t('DELIVERY_FORM_TOTAL_VAT')}
                  </div>
                </div>
              ) : (
                <div>
                  <div className="mb-1">
                    {money(0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                  </div>
                  <div>
                    {money(0)} {t('DELIVERY_FORM_TOTAL_VAT')}
                  </div>
                </div>
              )}

              {isAdmin && (
                <div style={{ maxWidth: '100%' }} className="mt-4">
                  <div>
                    {t('DELIVERY_FORM_SET_MANUALLY_PRICE')}
                    <Checkbox
                      className="ml-4 mb-2"
                      checked={overridePrice}
                      onChange={e => {
                        setOverridePrice(e.target.checked)
                        setCalculatePrice(0)
                      }}></Checkbox>
                  </div>
                </div>
              )}
            </div>
            {priceError.isPriceError ? (
              <div className="alert alert-danger mt-4" role="alert">
                {priceError.priceErrorMessage}
              </div>
            ) : null}
          </div>
        </>
      ) : null}
    </>
  )
}
