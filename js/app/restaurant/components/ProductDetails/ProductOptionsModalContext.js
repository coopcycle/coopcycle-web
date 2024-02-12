import React, { createContext, useState } from 'react'
import { isInitialValidValue } from './useProductOptions'

const ProductOptionsModalContext = createContext([ {}, () => {} ])

const ProductOptionsModalProvider = (props) => {
  const init = () => {
    const options = props.options.map(option => ({
      ...option,
      values: option.values.map(optionValue => ({
        ...optionValue,
        quantity: 0,
      })),
      valid: isInitialValidValue(option),
      total: 0
    }))

    const invalidOptions = options.filter(opt => !opt.valid)

    return {
      options,
      price: props.price,
      quantity: 1,
      total: props.price,
      missingMandatoryOptions: invalidOptions.length,
    }
  }

  const [ state, setState ] = useState(init())

  return (
    <ProductOptionsModalContext.Provider value={[ state, setState ]}>
      { props.children }
    </ProductOptionsModalContext.Provider>
  )
}

export {
  ProductOptionsModalContext,
  ProductOptionsModalProvider,
}
