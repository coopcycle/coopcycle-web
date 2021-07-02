import React, { useState } from 'react'
import { getCurrencySymbol } from '../i18n'
import { useTranslation } from 'react-i18next'

export default ({ packages, defaultValue, onChange }) => {

  const { t } = useTranslation()

  const [ unitPrice, setUnitPrice ] = useState(defaultValue.unitPrice || 0)
  const [ packageName, setPackageName ] = useState(defaultValue.packageName || 0)

  return (
    <div>
      <label className="mr-2">
        <input
          type="number"
          defaultValue={ unitPrice / 100 }
          size="4"
          min="0"
          step=".5"
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setUnitPrice(e.target.value * 100)
            onChange({
              packageName,
              unitPrice: e.target.value * 100,
            })
          }} />
          <span className="ml-2">{ getCurrencySymbol() }</span>
      </label>
      <label className="mr-2">
        <span className="mx-2">{ t('PRICE_RANGE_EDITOR.PER_PACKAGE') }</span>
        <select
          onChange={ e => {
            setPackageName(e.target.value)
            onChange({
              packageName: e.target.value,
              unitPrice,
            })
          }}>
          { packages.map(pkg => (<option key={ pkg }>{ pkg }</option>)) }
        </select>
      </label>
    </div>
  )
}
