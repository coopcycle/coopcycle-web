import React, { useState, useContext } from 'react'
import _ from 'lodash'

const defaultValuesRange = {
  lower: '0',
  upper: null,
  isUpperInfinite: true,
}

const getValuesRange = option => option.valuesRange || defaultValuesRange

function getInitialValidValue(option) {
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

const ProductOptionsModalContext = React.createContext([ {}, () => {} ])

const ProductOptionsModalProvider = (props) => {

  const options = props.options.map(option => ({
    ...option,
    values: option.values.map(optionValue => ({
      ...optionValue,
      quantity: 0,
    })),
    valid: getInitialValidValue(option),
    total: 0
  }))

  const invalidOptions = options.filter(opt => !opt.valid)

  const [ state, setState ] = useState({
    options,
    price: props.price,
    total: props.price,
    disabled: invalidOptions.length > 0,
  })

  return (
    <ProductOptionsModalContext.Provider value={[ state, setState ]}>
      { props.children }
    </ProductOptionsModalContext.Provider>
  )
}

const useProductOptions = () => {

  const [ state, setState ] = useContext(ProductOptionsModalContext)

  function setValueQuantity(option, optionValue, quantity) {

    const newOptions = state.options.map(opt => {

      if (opt.code === option.code) {

        const newValues = opt.values.map(val => {

          if (val.code === optionValue.code) {
            return {
              ...val,
              quantity,
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
      disabled: invalidOptions.length > 0,
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

  return {
    setValueQuantity,
    getValueQuantity,
  }
}

export {
  ProductOptionsModalContext,
  ProductOptionsModalProvider,
  useProductOptions,
  getValuesRange,
}
