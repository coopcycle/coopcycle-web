import React, { useState, useRef, useEffect } from 'react'
import { getCurrencySymbol } from '../i18n'
import { useTranslation } from 'react-i18next'

const UnitLabel = ({ unit }) => {

  const { t } = useTranslation()

  if (unit === 'vu') {

    return (
      <span>{ t('RULE_PICKER_LINE_VOLUME_UNITS') }</span>
    )
  }

  return (
    <span>{ unit }</span>
  )
}

const unitToAttribute = (unit) => {
  switch (unit) {
    case 'km':
      return 'distance'
    case 'kg':
      return 'weight'
    case 'vu':
      return 'packages.totalVolumeUnits()'
  }
}

const attributeToUnit = (attribute) => {
  switch (attribute) {
    case 'distance':
      return 'km'
    case 'weight':
      return 'kg'
    case 'packages.totalVolumeUnits()':
      return 'vu'
  }
}

const multiplyIfNeeded = (value, unit) => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value * 1000
  }

  return value
}

const divideIfNeeded = (value, unit) => {
  switch (unit) {
    case 'km':
    case 'kg':
      return value / 1000
  }

  return value
}

export default ({ defaultValue, onChange }) => {

  const { t } = useTranslation()

  const defaultAttribute = defaultValue.attribute || 'distance'

  const [ unit, setUnit ] = useState(attributeToUnit(defaultAttribute))

  const [ attribute, setAttribute ] = useState(defaultAttribute)
  const [ price, setPrice ] = useState(defaultValue.price || 0)
  const [ step, setStep ] = useState(defaultValue.step || 1000)
  const [ threshold, setThreshold ] = useState(defaultValue.threshold || 0)

  const stepEl = useRef(null)
  const thresholdEl = useRef(null)
  const initialLoad = useRef(true)

  useEffect(() => {
    if (!initialLoad.current) {
      onChange({
        attribute,
        price: price,
        step,
        threshold,
      })
    } else {
      initialLoad.current = false
    }
  }, [price, threshold, attribute, step])

  return (
    <div>
      <label className="mr-2">
        <input type="number" size="4"
          defaultValue={ price / 100 } min="0" step=".001"
          className="form-control d-inline-block no-number-input-arrow"
          style={{ width: '80px' }}
          onChange={ e => {
            setPrice(e.target.value * 100)
          }} />
        <span className="ml-2">{ getCurrencySymbol() }</span>
      </label>
      <label>
        <span className="mx-2">{ t('PRICE_RANGE_EDITOR.FOR_EVERY') }</span>
        <input type="number" size="4" min="0.1" step=".1" defaultValue={ divideIfNeeded(step, unit) }
          ref={ stepEl }
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setStep(multiplyIfNeeded(e.target.value, unit))
          }} />
        <select
          className="form-control d-inline-block align-top ml-2"
          style={{ width: '70px' }}
          defaultValue={ attributeToUnit(attribute) }
          onChange={ e => {
            setUnit(e.target.value)
            setAttribute(unitToAttribute(e.target.value))

            const newUnit = e.target.value

            if (newUnit === 'vu') {
              setStep(step / 1000)
              setThreshold(threshold / 1000)
            } else {
              setStep(step * 1000)
              setThreshold(threshold * 1000)
            }
          }}>
          <option value="km">km</option>
          <option value="kg">kg</option>
          <option value="vu">{ t('RULE_PICKER_LINE_VOLUME_UNITS') }</option>
        </select>
      </label>
      <label>
        <span className="mx-2">{ t('PRICE_RANGE_EDITOR.ABOVE') }</span>
        <input type="number" size="4" min="0" step=".1" defaultValue={ divideIfNeeded(threshold, unit) }
          ref={ thresholdEl }
          className="form-control d-inline-block"
          style={{ width: '80px' }}
          onChange={ e => {
            setThreshold(multiplyIfNeeded(e.target.value, unit))
          }} />
        <span className="ml-2">
          <UnitLabel unit={ unit } />
        </span>
      </label>
    </div>
  )
}
