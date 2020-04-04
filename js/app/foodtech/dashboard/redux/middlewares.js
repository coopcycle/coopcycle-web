import _ from 'lodash'

import {
  orderCreated,
  orderAccepted,
  orderRefused,
  orderCancelled,
  orderFulfilled
} from './actions'

let socket

export const socketIO = ({ dispatch, getState }) => {

  if (!socket) {

    socket = io(`//${window.location.hostname}`, {
      path: '/tracking/socket.io',
      query: {
        token: getState().jwt,
      },
      transports: [ 'websocket' ],
    })

    socket.on('order:created', event => {
      dispatch(orderCreated(event.order))
    })

    socket.on('order:accepted', event => {
      dispatch(orderAccepted(event.order))
    })

    socket.on('order:refused', event => {
      dispatch(orderRefused(event.order))
    })

    socket.on('order:cancelled', event => {
      dispatch(orderCancelled(event.order))
    })

    socket.on('order:fulfilled', event => {
      dispatch(orderFulfilled(event.order))
    })

  }

  return next => action => {

    return next(action)
  }
}

const pageTitle = (state, initialTitle) => {

  const { orders } = state
  const newOrders = _.filter(orders, o => o.state === 'new')
  if (newOrders.length > 0) {
    return `(${newOrders.length}) ${initialTitle}`
  }

  return initialTitle
}

export const title = ({ getState }) => {

  const initialTitle = document.title
  document.title = pageTitle(getState(), initialTitle)

  return next => action => {

    const result = next(action)
    document.title = pageTitle(getState(), initialTitle)

    return result
  }
}
