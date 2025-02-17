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
    <div className="override__form p-2 mb-1">
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
        taxRate={taxRate.amount}
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
        Math.round(calculatedPrice.VAT * 100),
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
        `${baseURL}/api/tax_rates`
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
    <div className="mb-1 pl-2">
      {
        deliveryId ?
          <>
            <div className="font-weight-bold mb-1 total__price">
              {!overridePrice
                ? t('DELIVERY_FORM_TOTAL_PRICE')
                : t('DELIVERY_FORM_OLD_PRICE')}
            </div>
            <div>
              {overridePrice ?
                <s>{money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</s> :
                <span data-testid="tax-included-previous">{money(deliveryPrice.VAT)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span>
              }
            </div>
            <div>
              {overridePrice ?
                <s>{money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</s> :
                <span>{money(deliveryPrice.exVAT)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
              }
            </div>
            {overridePrice && (
              <div className="mb-1">
                <div className="font-weight-bold mb-1 total__price">
                  {t('DELIVERY_FORM_NEW_PRICE')}
                </div>

                <span>
                  {money(calculatedPrice.exVAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}
                </span><br />
                <span data-testid="tax-included">
                  {money(calculatedPrice.VAT * 100 || 0)} {t('DELIVERY_FORM_TOTAL_VAT')}
                </span>
              </div>
            )}
          </>
        : 
          <>
            <div className="font-weight-bold mb-1 total__price">
              {t('DELIVERY_FORM_TOTAL_PRICE')}
            </div>
            {
              priceError.isPriceError ? (
                <div className="alert alert-danger" role="alert">
                  {priceError.priceErrorMessage}
                </div>
              ) :
              !overridePrice ?
                priceLoading ? 
                  <Spinner /> :
                  calculatedPrice.amount ? 
                    <>
                      <span>{money(calculatedPrice.amount - calculatedPrice.tax.amount,)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span><br />
                      <span data-testid="tax-included">{money(calculatedPrice.amount)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
                    </> :
                    <>
                      <span>{money(0)} {t('DELIVERY_FORM_TOTAL_EX_VAT')}</span><br/>
                      <span>{money(0)} {t('DELIVERY_FORM_TOTAL_VAT')}</span>
                    </>
                : null
            }
          </>
        }

        {isAdmin && (
          <div className="mt-2">
            <div
              style={{ maxWidth: '100%', cursor: 'pointer' }}
              onClick={() => {
                setOverridePrice(!overridePrice)
                setCalculatePrice(0)
              }}
            >
              <div>
                <span>{t('DELIVERY_FORM_SET_MANUALLY_PRICE')}</span>
                <Checkbox
                  className="ml-4 mb-1"
                  name="delivery.override_price"
                  checked={overridePrice}
                  onChange={e => {
                    e.stopPropagation()
                    setOverridePrice(e.target.checked)
                    setCalculatePrice(0)
                  }}></Checkbox>
              </div>
            </div>
            {overridePrice && (
              <OverridePriceForm
                setCalculatePrice={setCalculatePrice}
                taxRate={taxRate}
              />
            )}
          </div>
        )}
    </div>
  )
}
