import React from 'react'
import { getValuesRange, useProductOptions } from './ProductOptionsModalContext'
import { useTranslation } from 'react-i18next'

const OptionValueLabel = ({ option, optionValue }) => (
  <span>
    <span>{ optionValue.value }</span>
    { (option.strategy === 'option_value' && optionValue.price > 0) && (
      <span>
        <br />
        <small className="text-muted">+{ (optionValue.price / 100).formatMoney() }</small>
      </span>
    ) }
  </span>
)

const OptionValue = ({ index, option, optionValue }) => {

  const { setValueQuantity } = useProductOptions()

  return (
    <div className="radio m-0">
      <label className="d-flex align-items-center">
        <input
          type="radio"
          name={ `options[${index}][code]` }
          value={ optionValue.code }
          onClick={ () => {
            window._paq.push(['trackEvent', 'Checkout', 'selectOption'])
            setValueQuantity(option, optionValue, 1)
          }} />
        <OptionValueLabel option={ option } optionValue={ optionValue } />
      </label>
    </div>
  )
}

const AdditionalOptionValue = ({ index, valueIndex, option, optionValue }) => {

  const { setValueQuantity, getValueQuantity } = useProductOptions()
  const valuesRange = getValuesRange(option)
  const quantity = getValueQuantity(option, optionValue)

  let inputProps = {}
  if (!valuesRange.isUpperInfinite) {
    inputProps = { ...inputProps, max: valuesRange.upper }
  }

  const realIndex = index + valueIndex

  return (
    <div className="product-option-item-range">
      <input type="hidden" name={ `options[${realIndex}][code]` } value={ optionValue.code } />
      <input
        name={ `options[${realIndex}][quantity]` }
        type="number"
        step="1"
        min="0"
        value={ quantity }
        onChange={ e => {
          setValueQuantity(option, optionValue, parseInt(e.currentTarget.value, 10))
        }}
        { ...inputProps } />
      <label htmlFor={ '' } onClick={ () => {
        setValueQuantity(option, optionValue, quantity + 1)
      }}>
        <OptionValueLabel option={ option } optionValue={ optionValue } />
      </label>
      <div className="product-option-item-range-buttons">
        <button className="button-icon--decrement" type="button" onClick={ () => {
          quantity > 0 && setValueQuantity(option, optionValue, quantity - 1)
        }}>
          <i className="fa fa-lg fa-minus-circle"></i>
        </button>
        <button className="button-icon--increment" type="button" onClick={ () => {
          setValueQuantity(option, optionValue, quantity + 1)
        }}>
          <i className="fa fa-lg fa-plus-circle"></i>
        </button>
      </div>
    </div>
  )
}

const ValuesRange = ({ option }) => {

  const { t } = useTranslation()

  if (option.additional) {

    const valuesRange = getValuesRange(option)

    const min = parseInt(valuesRange.lower, 10)
    const max = valuesRange.isUpperInfinite ? Infinity : parseInt(valuesRange.upper, 10)

    if (min === 0 && max !== Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_UP_TO', {
          count: max,
        }) }</small>
      )
    }

    if (min > 0 && max === Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_AT_LEAST', {
          count: min,
        }) }</small>
      )
    }

    if (min > 0 && max !== Infinity) {
      return (
        <small className="ml-2">{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE', {
          min,
          max,
        }) }</small>
      )
    }
  }

  return null
}

export const OptionGroup = ({ index, option }) => (
  <div id={`product-option-group-${option.code}`} className="pt-4">
    <h4 className="m-0 mb-4">
      <span>{option.name}</span>
      <ValuesRange option={option}/>
    </h4>
    <div className="list-group">
      {option.values.map((optionValue, optionValueIndex) => {

        return (
          <div className="list-group-item product-option-item"
               key={`option-value-${optionValueIndex}`}>
            {!option.additional && <OptionValue
              option={option}
              optionValue={optionValue}
              index={index}
              valueIndex={optionValueIndex}/>
            }
            {option.additional && <AdditionalOptionValue
              option={option}
              optionValue={optionValue}
              index={index}
              valueIndex={optionValueIndex}/>
            }
          </div>
        )
      })}
    </div>
  </div>
)
