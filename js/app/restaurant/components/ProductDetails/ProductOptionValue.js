import React from 'react'
import { getValuesRange, useProductOptions } from './ProductOptionsModalContext'

const OptionValueLabel = ({ option, optionValue }) => (
  <>
    <span className="product-option-item-label__name">{optionValue.value}</span>
    {(option.strategy === 'option_value' && optionValue.price > 0) && (
      <span className="product-option-item-label__price">+{(optionValue.price /
        100).formatMoney()}</span>
    )}
  </>
)

export const OptionValue = ({ index, option, optionValue }) => {

  const { setValueQuantity } = useProductOptions()

  return (
    <div
      className="radio m-0 product-option-item product-option-item-single-choice">
      <label className="product-option-item-label">
        <input
          type="radio"
          name={`options[${index}][code]`}
          value={optionValue.code}
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

  const { setValueQuantity, getValueQuantity } = useProductOptions()
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
        className="product-option-item-label"
        htmlFor={''}
        onClick={() => {
          setValueQuantity(option, optionValue, quantity + 1)
        }}>
        <OptionValueLabel option={option} optionValue={optionValue}/>
      </label>
      <button
        className="quantity-decrement"
        type="button"
        onClick={() => {
          quantity > 0 &&
          setValueQuantity(option, optionValue, quantity - 1)
        }}>
        <div>-</div>
      </button>
      <input
        name={`options[${realIndex}][quantity]`}
        type="number"
        step="1"
        min="0"
        value={quantity}
        onChange={e => {
          setValueQuantity(option, optionValue,
            parseInt(e.currentTarget.value, 10))
        }}
        {...inputProps} />
      <button
        className="quantity-increment"
        type="button"
        onClick={() => {
          setValueQuantity(option, optionValue, quantity + 1)
        }}>
        <div>+</div>
      </button>
    </div>
  )
}
