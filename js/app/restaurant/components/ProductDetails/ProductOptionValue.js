import React from 'react'
import { getValuesRange, useProductOptions } from './useProductOptions'
import {
  DecrementQuantityButton,
  IncrementQuantityButton,
} from '../ChangeQuantityButton'

const OptionValueLabel = ({ option, optionValue }) => (
  <>
    <div className="product-option-item__name">{optionValue.name}</div>
    {(option.additionalType === 'option_value' && optionValue.offers.price > 0) && (
      <div className="product-option-item__price">+{(optionValue.offers.price / 100).formatMoney()}</div>
    )}
  </>
)

export const OptionValue = ({ index, option, optionValue }) => {

  const { setValueQuantity } = useProductOptions()

  return (
    <div
      className="product-option-item product-option-item-single-choice">
      <label className="flex items-center gap-2 hover:bg-base-300 cursor-pointer">
        <input
          type="radio"
          name={`options[${index}][code]`}
          value={optionValue.identifier}
          onClick={() => {
            window._paq.push(['trackEvent', 'Checkout', 'selectOption'])
            setValueQuantity(option, optionValue, 1)
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

  const inputProps = !valuesRange.isUpperInfinite ? { max: valuesRange.upper } : {}

  const realIndex = index + valueIndex

  return (
    <div className="product-option-item product-option-item-range">
      <input
        type="hidden" name={`options[${realIndex}][code]`}
        value={optionValue.identifier}/>
      <label
        className="hover:bg-base-300 cursor-pointer"
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
        className="input"
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
