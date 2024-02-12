import React from 'react'
import { useTranslation } from 'react-i18next'
import { AdditionalOptionValue, OptionValue } from './ProductOptionValue'
import { getValuesRange } from './useProductOptions'

const ValuesRange = ({ option }) => {

  const { t } = useTranslation()

  if (option.additional) {

    const valuesRange = getValuesRange(option)

    const min = parseInt(valuesRange.lower, 10)
    const max = valuesRange.isUpperInfinite ? Infinity : parseInt(valuesRange.upper, 10)

    if (min === 0 && max !== Infinity) {
      return (
        <small>{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_UP_TO', {
          count: max,
        }) }</small>
      )
    }

    if (min > 0 && max === Infinity) {
      return (
        <small>{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE_AT_LEAST', {
          count: min,
        }) }</small>
      )
    }

    if (min > 0 && max !== Infinity) {
      return (
        <small>{ t('CART_PRODUCT_OPTIONS_VALUES_RANGE', {
          min,
          max,
        }) }</small>
      )
    }
  }

  return null
}

export const OptionGroup = ({ index, option }) => (
  <div id={`product-option-group-${option.code}`}>
    <div>{option.name}</div>
    <ValuesRange option={option}/>
    <div className="mt-2">
      {option.values.map((optionValue, optionValueIndex) => {

        return (
          <div key={`option-value-${optionValueIndex}`}>
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
