import React from 'react'
import { Field, useFormikContext } from 'formik'
import { useTranslation } from 'react-i18next'
import { Input } from 'antd'
import PriceVATConverter from './PriceVATConverter'

export default ({ setCalculatePrice, taxRate }) => {
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
        taxRate={taxRate?.amount ?? 0}
        setPrices={setCalculatePrice}
      />
    </div>
  )
}
