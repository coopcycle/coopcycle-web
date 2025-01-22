import React from 'react'
import { Checkbox, Input, InputNumber } from 'antd'
import { useFormikContext, Field } from 'formik'
import './OverridePrice.scss'

const getCurrencyCode = () => {
  const { currencyCode } = document.body.dataset
  return currencyCode || 'EUR' // 'EUR' comme valeur par défaut si non défini
}

export default ({
  deliveryId,
  setOverridePrice,
  overridePrice,
  setCalculatePrice,
}) => {
  console.log(deliveryId)

  const { setFieldValue } = useFormikContext()

  return (
    <div style={{ maxWidth: '100%' }} className="mt-4">
      <div>
        Définir le prix manuellement
        <Checkbox
          className="ml-4 mb-2"
          checked={overridePrice}
          onChange={e => {
            setOverridePrice(e.target.checked)
            setCalculatePrice(0)
          }}></Checkbox>
      </div>

      {overridePrice && (
        <div className="override__form pt-4 pb-2 mt-2 border-top">
          <div className="override__form__variant-name">
            <label
              className="override__form__variant-name___label font-weight-bold"
              htmlFor="variantName">
              Nom du produit
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
                Le nom qui apparaîtra dans le résumé de la commande et la
                facture
              </div>
            </div>
          </div>
          <div className="override__form__variant-price">
            <label
              className="override__form__variant-price___label font-weight-bold"
              htmlFor="variantPrice">
              Prix TCC
            </label>
            <Field
              className="override__form__variant-price___input"
              name={'variantPrice'}>
              {({ field }) => (
                <InputNumber
                  id="variantPrice"
                  controls={false}
                  prefix={getCurrencyCode()}
                  {...field}
                  onChange={value => {
                    setFieldValue('variantPrice', value)
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
