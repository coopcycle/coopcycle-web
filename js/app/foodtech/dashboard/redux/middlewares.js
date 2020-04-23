import _ from 'lodash'
import { Howl } from 'howler'

import {
  orderCreated,
  orderAccepted,
  orderRefused,
  orderCancelled,
  orderFulfilled,
  ORDER_CREATED,
} from './actions'

import { asText } from '../../../components/ShippingTimeRange'
import i18n from '../../../i18n'

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

const sound = new Howl({
  src: ['/sounds/383624__newagesoup__bicycle-bell-13.wav']
})

function notify(title, payload) {
  var n = new Notification(title, payload)
  n.onshow = function () {
    sound.play()
  }
  n.onclick = function () {
    window.focus()
  }
}

export const notification = ({ getState }) => {

  if (window.Notification && Notification.permission !== 'granted') {
    Notification.requestPermission((status) => {
      if (status === 'granted') {
        notify(i18n.t('NOTIFICATIONS_ENABLED'), { body: '🔔🔔🔔' })
      }
    })
  }

  return next => action => {

    const prevState = getState()
    const result = next(action)
    const state = getState()

    if (action.type === ORDER_CREATED && (state.orders.length > prevState.orders.length)) {
      const { restaurant, shippingTimeRange } = action.payload
      if (window.Notification && Notification.permission === 'granted') {
        notify(restaurant.name, {
          body: asText(shippingTimeRange),
          tag: 'order:created',
        })
      }
    }

    return result
  }
}
