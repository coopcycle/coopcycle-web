import _ from 'lodash'
import { Howl } from 'howler'
import Centrifuge from 'centrifuge'

import {
  orderCreated,
  orderAccepted,
  orderRefused,
  orderCancelled,
  orderFulfilled,
  ORDER_CREATED,
  initHttpClient,
  INIT_HTTP_CLIENT,
  refreshTokenSuccess,
} from './actions'

import createHttpClient from '../../../client'
import { asText } from '../../../components/ShippingTimeRange'
import i18n from '../../../i18n'

let centrifuge

export const socketIO = ({ dispatch, getState }) => {

  if (!centrifuge) {

    const { token, namespace, username } = getState().centrifugo

    const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'

    centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`)
    centrifuge.setToken(token)
    centrifuge.subscribe(`${namespace}_events#${username}`, message => {
      const { event } = message.data

      switch (event.name) {
        case 'order:created':
          dispatch(orderCreated(event.data.order))
          break
        case 'order:accepted':
          dispatch(orderAccepted(event.data.order))
          break
        case 'order:refused':
          dispatch(orderRefused(event.data.order))
          break
        case 'order:cancelled':
          dispatch(orderCancelled(event.data.order))
          break
        case 'order:fulfilled':
          dispatch(orderFulfilled(event.data.order))
          break
      }
    })
    centrifuge.connect()

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

export const httpClient = ({ dispatch, getState }) => {

  const fetchToken = window.Routing.generate('profile_jwt')

  const httpClient = createHttpClient(
    getState().jwt,
    () => new Promise((resolve) => {
      // TODO Check response is OK, reject promise
      $.getJSON(fetchToken).then(result => resolve(result.jwt))
    }),
    token => dispatch(refreshTokenSuccess(token))
  )

  return next => action => {

    const prevState = getState()

    if (!prevState.httpClient && action.type !== INIT_HTTP_CLIENT) {
      dispatch(initHttpClient(httpClient))
    }

    return next(action)
  }
}
