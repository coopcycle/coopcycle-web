import React from 'react'
import { Checkbox, Input, InputNumber } from 'antd'
import { useFormikContext, Field } from 'formik'
import { useTranslation } from 'react-i18next'
import './OverridePrice.scss'

const getCurrencyCode = () => {
  const { currencyCode } = document.body.dataset
  return currencyCode || 'EUR' // 'EUR' comme valeur par défaut si non défini
}

export default ({ setOverridePrice, overridePrice, setCalculatePrice }) => {
  const { setFieldValue } = useFormikContext()
  const { t } = useTranslation()

  return (
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

      {overridePrice && (
        <div className="override__form p-2 mt-2 border-top">
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
              <div className="small text-muted">
                {t('DELIVERY_FORM_NAME_INSTRUCTION')}
              </div>
            </div>
          </div>
          <div className="override__form__variant-price">
            <label
              className="override__form__variant-price___label font-weight-bold"
              htmlFor="variantPrice">
              {t('DELIVERY_FORM_VAT_PRICE')}
            </label>
            <Field
              className="override__form__variant-price___input"
              name={'variantPrice'}>
              {({ field }) => (
                <InputNumber
                  id="variantPrice"
                  controls={false}
                  value={field.value / 100}
                  prefix={getCurrencyCode()}
                  onChange={value => {
                    setFieldValue('variantPrice', value * 100)
                    setCalculatePrice({
                      amount: value * 100,
                      tax: { amount: 0 },
                    })
                  }}
                />
              )}
            </Field>
          </div>
        </div>
      )}
    </div>
  )
}
