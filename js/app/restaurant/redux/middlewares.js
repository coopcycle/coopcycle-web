import {mapAddressFields, playerUpdateEvent, SET_PLAYER_TOKEN} from './actions'
import { createCentrifuge, subscribe } from '../../centrifugo/client'

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

export const playerWebsocket = ({dispatch, getState}) => {
  return next => action => {

    const prevState = getState()
    const result = next(action)
    const { player } = getState()

    if (action.type === SET_PLAYER_TOKEN && prevState.player.token === null && player.token)  {
      // The player has no session, so no token can be reissued: the connection
      // ends when this one expires.
      const centrifuge = createCentrifuge(player.centrifugo.token, { refresh: false })

      subscribe(centrifuge, player.centrifugo.channel, data => {
        dispatch(playerUpdateEvent(data.event.data.order))
      })
      centrifuge.connect()
    }

    return result

  }
}
