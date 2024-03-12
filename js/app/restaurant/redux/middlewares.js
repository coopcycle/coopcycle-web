import {mapAddressFields, playerUpdateEvent, SET_PLAYER_TOKEN} from './actions'
import Centrifuge from "centrifuge"

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
      const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'
      const centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`, {
        // In this case, we don't refresh the connection
        // https://github.com/centrifugal/centrifuge-js#refreshendpoint
        refreshAttempts: 0,
        onRefresh: function(ctx, cb) {
          cb({ status: 403 })
        }
      })

      centrifuge.setToken(player.centrifugo.token)

      centrifuge.subscribe(player.centrifugo.channel, message => {
        dispatch(playerUpdateEvent(message.data.event.data.order))
      })
      centrifuge.connect()
    }

    return result

  }
}
