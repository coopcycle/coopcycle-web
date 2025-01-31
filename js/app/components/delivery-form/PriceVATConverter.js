import React, { useState } from 'react'
import { InputNumber } from 'antd'
import { useTranslation } from 'react-i18next'
import './PriceVATConverter.scss'

const getCurrencySymbol = () => {
  const { currencySymbol } = document.body.dataset
  return currencySymbol
}

export default ({ taxRate, setPrices }) => {
  const [values, setValues] = useState({ VAT: 0, exVAT: 0 })

  const { t } = useTranslation()

  return (
    <div className="row">
      <div className="col-xs-6 field">
        <label
          className="variant-price-exVAT___label font-weight-bold mr-3"
          htmlFor="variantPriceExVAT">
          {t('DELIVERY_FORM_EXVAT_PRICE')}
        </label>

        <InputNumber
          id="variantPriceExVAT"
          controls={false}
          prefix={getCurrencySymbol()}
          value={values.exVAT}
          onChange={value => {
            const newValues = {
              exVAT: value,
              VAT: Math.round((value * 100) * (taxRate + 1)) / 100,
            }
            console.log(newValues)
            setValues(newValues)
            setPrices(newValues)
          }}
        />
      </div>

      <div className="col-xs-6 field">
        <label
          className="variant-price-VAT___label font-weight-bold mr-3"
          htmlFor="variantPriceVAT">
          {t('DELIVERY_FORM_VAT_PRICE')}
        </label>

        <InputNumber
          id="variantPriceVAT"
          controls={false}
          prefix={getCurrencySymbol()}
          value={values.VAT}
          onChange={value => {
            const newValues = {
              exVAT: Math.round((value * 100) / (taxRate + 1)) / 100,
              VAT: value,
            }
            setValues(newValues)
            setPrices(newValues)
          }}
        />
      </div>
    </div>
  )
}
