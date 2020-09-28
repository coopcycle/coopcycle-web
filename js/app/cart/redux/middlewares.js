import { mapAddressFields } from './actions'

/**
 * This middleware checks if the shipping address was updated,
 * and updates the value of the mapped HTML elements
 */
export const updateFormElements = ({ dispatch, getState }) => {

  return next => action => {

    const prevState = getState()
    const result = next(action)
    const state = getState()

    if (state.cart.shippingAddress !== prevState.cart.shippingAddress) {
        dispatch(mapAddressFields(state.cart.shippingAddress))
    }

    return result
  }
}
