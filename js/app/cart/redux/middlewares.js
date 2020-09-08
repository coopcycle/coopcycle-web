import _ from 'lodash'

/**
 * This middleware checks if the shipping address was updated,
 * and updates the value of the mapped HTML elements
 */
export const updateFormElements = ({ getState }) => {

  return next => action => {

    const prevState = getState()
    const result = next(action)
    const state = getState()

    if (state.cart.shippingAddress !== prevState.cart.shippingAddress) {
        _.forEach(state.addressFormElements, (el, key) => {
            const value = _.get(state.cart.shippingAddress, key)
            if (value) {
                el.value = value
            }
        })
    }

    return result
  }
}
