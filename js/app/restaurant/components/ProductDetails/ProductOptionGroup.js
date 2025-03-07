import React from 'react'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'

import { AdditionalOptionValue, OptionValue } from './ProductOptionValue'
import { getValuesRange, isMandatory, isValid } from './useProductOptions'

const ValuesRange = ({ option }) => {

  const { t } = useTranslation()
  let range = ''

  if (option.additional) {

    const valuesRange = getValuesRange(option)

    const min = parseInt(valuesRange.lower, 10)
    const max = valuesRange.isUpperInfinite ? Infinity : parseInt(
      valuesRange.upper, 10)

    if (min === 0 && max !== Infinity) {
      range = t('CART_PRODUCT_OPTIONS_VALUES_RANGE_UP_TO', { count: max })
    }

    if (min > 0 && max === Infinity) {
      range = t('CART_PRODUCT_OPTIONS_VALUES_RANGE_AT_LEAST', { count: min })
    }

    if (min > 0 && max !== Infinity) {
      range = t('CART_PRODUCT_OPTIONS_VALUES_RANGE', { min, max })
    }
  }

  if (range) {
    return (
      <small className="product-option-group__values_range">{ range }</small>
    )
  } else {
    return null
  }
}

export const OptionGroup = ({ index, option }) => {
  const isSelectedAndNotValid = !isMandatory(option) && !isValid(option)

  return (
    <div id={ `product-option-group-${ option.code }` }
         className={ classNames('product-option-group', {
           'product-option-group--invalid': isSelectedAndNotValid,
         }) }>
      <div className="product-option-group__name">{ option.name }</div>
      <ValuesRange option={ option } />
      <div className="mt-2">
        { option.values.map((optionValue, optionValueIndex) => (
          <div key={ `option-value-${ optionValueIndex }` }>
            { !option.additional && <OptionValue
              option={ option }
              optionValue={ optionValue }
              index={ index }
              valueIndex={ optionValueIndex } />
            }
            { option.additional && <AdditionalOptionValue
              option={ option }
              optionValue={ optionValue }
              index={ index }
              valueIndex={ optionValueIndex } />
            }
          </div>
        )) }
      </div>
    </div>
  )
}
