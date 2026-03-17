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

const parseValuesRange = (rangeStr) => {
  if (!rangeStr) return defaultValuesRange
  const parts = rangeStr.replace(/[\[\]()\s]/g, '').split(',')
  const lower = parts[0] || '0'
  const upper = parts[1]
  const isUpperInfinite = !upper || upper === ''
  return {
    lower,
    upper: isUpperInfinite ? null : upper,
    isUpperInfinite,
  }
}

export const getValuesRange = option => parseValuesRange(option.valuesRange)

export function isMandatory(option) {
  if (option.additional) {
    const valuesRange = getValuesRange(option)
    const min = parseInt(valuesRange.lower, 10)
    return min > 0
  } else {
    return true
  }
}

export function isValid(option) {
  const totalQuantity = option.hasMenuItem.reduce(
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

  const [state, setState] = useContext(ProductOptionsModalContext)

  console.log('useProductOptions', state)

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

      if (opt.identifier === option.identifier) {

        const newValues = opt.hasMenuItem.map(val => {

          if (val.identifier === optionValue.identifier) {
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
          hasMenuItem: newValues,
          total: newValues.reduce((total, val) => total + (val.offers.price * val.quantity), 0),
        }
      }

      return opt
    })

    setState({
      ...state,
      options: newOptions,
      total: state.price + _.sumBy(newOptions, 'total'),
    })
  }

  function getValueQuantity(option, optionValue) {

    const opt = _.find(state.options, opt => opt.identifier === option.identifier)
    if (opt) {
      const val = _.find(opt.hasMenuItem, val => val.identifier === optionValue.identifier)
      if (val) {
        return val.quantity
      }
    }

    return 0
  }

  function getValueQuantityInput(option, optionValue) {

    const opt = _.find(state.options, opt => opt.identifier === option.identifier)
    if (opt) {
      const val = _.find(opt.hasMenuItem, val => val.identifier === optionValue.identifier)
      if (val) {
        return val.quantityInput ?? getValueQuantity(option, optionValue)
      }
    }

    return getValueQuantity(option, optionValue)
  }

  function containsOptionValues(optionValueIds) {

    return -1 !== _.findIndex(state.options, opt => {
      const selectedOptVals = _.filter(opt.hasMenuItem, optVal => optVal.quantity > 0).map(optVal => optVal['@id'])
      return _.intersection(selectedOptVals, optionValueIds).length > 0;
    })
  }

  return {
    getValueQuantity: getValueQuantityInput,
    setValueQuantity,
    incrementValueQuantity,
    decrementValueQuantity,
    containsOptionValues,
  }
}
