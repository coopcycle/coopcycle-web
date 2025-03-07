import React, { createContext, useState } from 'react'

const ProductOptionsModalContext = createContext([ {}, () => {} ])

const ProductOptionsModalProvider = (props) => {
  const init = () => {
    const options = props.options.map(option => ({
      ...option,
      values: option.values.map(optionValue => ({
        ...optionValue,
        quantity: 0,
      })),
      total: 0
    }))

    return {
      options,
      price: props.price,
      quantity: 1,
      total: props.price,
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
