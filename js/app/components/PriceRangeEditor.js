import React, { useState } from 'react'
import { getCurrencySymbol } from '../i18n'
import { useTranslation } from 'react-i18next'

export default ({ defaultValue, onChange }) => {

  const { t } = useTranslation()

  const defaultAttribute = defaultValue.attribute || 'distance'

  const [ unit, setUnit ] = useState(defaultAttribute ? 'km' : 'kg')

  const [ attribute, setAttribute ] = useState(defaultAttribute)
  const [ price, setPrice ] = useState(defaultValue.price || 0)
  const [ step, setStep ] = useState(defaultValue.step || 0)
  const [ threshold, setThreshold ] = useState(defaultValue.threshold || 0)

  return (
    <div>
      <label className="mr-2">
        <input type="number" size="4"
          defaultValue={ price / 100 } min="0" step=".5"
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setPrice(e.target.value * 100)
            onChange({
              attribute,
              price: e.target.value * 100,
              step,
              threshold,
            })
          }} />
        <span className="ml-2">{ getCurrencySymbol() }</span>
      </label>
      <label>
        <span className="mx-2">{ t('PRICE_RANGE_EDITOR.FOR_EVERY') }</span>
        <input type="number" size="4" min="0" step=".5" defaultValue={ step / 1000 }
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setStep(e.target.value * 1000)
            onChange({
              attribute,
              price,
              step: e.target.value * 1000,
              threshold,
            })
          }} />
        <select
          className="form-control d-inline-block align-top ml-2"
          style={{ width: '70px' }}
          defaultValue={ attribute === 'distance' ? 'km' : 'kg' }
          onChange={ e => {
            setUnit(e.target.value)
            setAttribute(e.target.value === 'km' ? 'distance' : 'weight')
            onChange({
              attribute: e.target.value === 'km' ? 'distance' : 'weight',
              price,
              step,
              threshold,
            })
          }}>
          <option value="km">km</option>
          <option value="kg">kg</option>
        </select>
      </label>
      <label>
        <span className="mx-2">{ t('PRICE_RANGE_EDITOR.ABOVE') }</span>
        <input type="number" size="4" min="0" step=".5" defaultValue={ threshold / 1000 }
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setThreshold(e.target.value * 1000)
            onChange({
              attribute,
              price,
              step,
              threshold: e.target.value * 1000,
            })
          }} />
        <span className="ml-2">{ unit }</span>
      </label>
    </div>
  )
}
