import { useContext } from 'react'
import {
  ProductOptionsModalContext
} from './ProductOptionsModalContext'

export const useProduct = () => {

  const [state, setState] = useContext(ProductOptionsModalContext)

  const setQuantity = (input) => {
    const quantity = parseInt(input, 10)

    if (Number.isNaN(quantity)) {
      setState({
        ...state,
        quantity: 1,
        quantityInput: NaN, // prevents displaying invalid characters in the input field
      })
    } else {
      setState({
        ...state,
        quantity,
        quantityInput: input,
      })
    }
  }

  const incrementQuantity = () => {
    setQuantity(state.quantity + 1)
  }

  const decrementQuantity = () => {
    if (state.quantity > 1) {
      setQuantity(state.quantity - 1)
    }
  }

  return {
    price: state.price,
    quantity: state.quantityInput ?? state.quantity,
    setQuantity,
    incrementQuantity,
    decrementQuantity,
  }
}
