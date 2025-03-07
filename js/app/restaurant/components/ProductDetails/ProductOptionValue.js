import React from 'react'
import { getValuesRange, useProductOptions } from './useProductOptions'
import {
  DecrementQuantityButton,
  IncrementQuantityButton,
} from '../ChangeQuantityButton'

const OptionValueLabel = ({ option, optionValue }) => (
  <>
    <div className="product-option-item__name">{optionValue.value}</div>
    {(option.strategy === 'option_value' && optionValue.price > 0) && (
      <div className="product-option-item__price">+{(optionValue.price / 100).formatMoney()}</div>
    )}
  </>
)

export const OptionValue = ({ index, option, optionValue }) => {

  const { incrementValueQuantity } = useProductOptions()

  return (
    <div
      className="radio m-0 product-option-item product-option-item-single-choice">
      <label>
        <input
          type="radio"
          name={`options[${index}][code]`}
          value={optionValue.code}
          onClick={() => {
            window._paq.push(['trackEvent', 'Checkout', 'selectOption'])
            incrementValueQuantity(option, optionValue)
          }}/>
        <OptionValueLabel option={option} optionValue={optionValue}/>
      </label>
    </div>
  )
}

export const AdditionalOptionValue = ({
  index,
  valueIndex,
  option,
  optionValue,
}) => {

  const { getValueQuantity, setValueQuantity, incrementValueQuantity, decrementValueQuantity } = useProductOptions()
  const valuesRange = getValuesRange(option)
  const quantity = getValueQuantity(option, optionValue)

  let inputProps = {}
  if (!valuesRange.isUpperInfinite) {
    inputProps = { ...inputProps, max: valuesRange.upper }
  }

  const realIndex = index + valueIndex

  return (
    <div className="product-option-item product-option-item-range">
      <input
        type="hidden" name={`options[${realIndex}][code]`}
        value={optionValue.code}/>
      <label
        htmlFor={''}
        onClick={() => {
          incrementValueQuantity(option, optionValue)
        }}>
        <OptionValueLabel option={option} optionValue={optionValue}/>
      </label>
      <DecrementQuantityButton
        onClick={ () => {
          decrementValueQuantity(option, optionValue)
        } } />
      <input
        name={`options[${realIndex}][quantity]`}
        type="number"
        step="1"
        min="0"
        value={quantity}
        onChange={e => {
          setValueQuantity(option, optionValue, e.currentTarget.value)
        }}
        {...inputProps} />
      <IncrementQuantityButton
        onClick={ () => {
          incrementValueQuantity(option, optionValue)
        } } />
    </div>
  )
}
