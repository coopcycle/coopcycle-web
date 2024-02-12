import { useContext } from 'react'
import _ from 'lodash'
import {
  ProductOptionsModalContext,
} from './ProductOptionsModalContext'

const defaultValuesRange = {
  lower: '0',
  upper: null,
  isUpperInfinite: true,
}

export const getValuesRange = option => option.valuesRange || defaultValuesRange

export function isInitialValidValue(option) {
  if (!option.additional) {
    return false
  }

  const valuesRange = getValuesRange(option)
  const min = parseInt(valuesRange.lower, 10)

  return min === 0
}


function isValidOption(option, values) {

  const totalQuantity = values.reduce(
    (quantity, val) => quantity + val.quantity,
    0
  )

  if (!option.additional) {
    return totalQuantity > 0
  }

  const valuesRange = getValuesRange(option)
  const min = parseInt(valuesRange.lower, 10)

  if (totalQuantity < min) {
    return false
  }

  if (!valuesRange.isUpperInfinite) {
    const max = parseInt(valuesRange.upper, 10)
    if (totalQuantity > max) {
      return false
    }
  }

  return true
}

export const useProductOptions = () => {

  const [ state, setState ] = useContext(ProductOptionsModalContext)

  function setValueQuantity(option, optionValue, input) {
    const quantity = parseInt(input, 10)
    _setValueQuantity(option, optionValue, quantity)
  }

  function incrementValueQuantity(option, optionValue) {
    const quantity = getValueQuantity(option, optionValue)
    _setValueQuantity(option, optionValue, quantity + 1)
  }

  function decrementValueQuantity(option, optionValue) {
    const quantity = getValueQuantity(option, optionValue)
    if (quantity > 0) {
      _setValueQuantity(option, optionValue, quantity - 1)
    }
  }

  function _setValueQuantity(option, optionValue, quantity) {
    const newOptions = state.options.map(opt => {

      if (opt.code === option.code) {

        const newValues = opt.values.map(val => {

          if (val.code === optionValue.code) {
            if (Number.isNaN(quantity)) {
              return {
                ...val,
                quantity: 0,
                quantityInput: NaN, // prevents displaying invalid characters in the input field
              }
            } else {
              return {
                ...val,
                quantity,
                quantityInput: `${quantity}`
              }
            }
          }

          return opt.additional ? val : { ...val, quantity: 0 }
        })

        return {
          ...opt,
          values: newValues,
          total: newValues.reduce((total, val) => total + (val.price * val.quantity), 0),
          valid: isValidOption(opt, newValues),
        }
      }

      return opt
    })

    const invalidOptions = newOptions.filter(opt => !opt.valid)

    setState({
      ...state,
      options: newOptions,
      total: state.price + _.sumBy(newOptions, 'total'),
      missingMandatoryOptions: invalidOptions.length,
    })
  }

  function getValueQuantity(option, optionValue) {

    const opt = _.find(state.options, opt => opt.code === option.code)
    if (opt) {
      const val = _.find(opt.values, val => val.code === optionValue.code)
      if (val) {
        return val.quantity
      }
    }

    return 0
  }

  function getValueQuantityInput(option, optionValue) {

    const opt = _.find(state.options, opt => opt.code === option.code)
    if (opt) {
      const val = _.find(opt.values, val => val.code === optionValue.code)
      if (val) {
        return val.quantityInput ?? getValueQuantity(option, optionValue)
      }
    }

    return getValueQuantity(option, optionValue)
  }

  return {
    getValueQuantity: getValueQuantityInput,
    setValueQuantity,
    incrementValueQuantity,
    decrementValueQuantity,
  }
}
