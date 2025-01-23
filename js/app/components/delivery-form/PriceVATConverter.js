import React, { useState } from 'react'
import { InputNumber } from 'antd'
import { useTranslation } from 'react-i18next'

const getCurrencySymbol = () => {
  const { currencySymbol } = document.body.dataset
  return currencySymbol
}

export default ({ amount, setPrices }) => {
  const [values, setValues] = useState({ VAT: 0, exVAT: 0 })

  const { t } = useTranslation()

  return (
    <div className="row">
      <div className="col-xs-6">
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
              exVAT: (value / (amount + 1)).toFixed(2),
              VAT: value,
            }
            setValues(newValues)
            setPrices(newValues)
          }}
        />
      </div>

      <div className="col-xs-6">
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
              exVAT: (value / (amount + 1)).toFixed(2),
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
