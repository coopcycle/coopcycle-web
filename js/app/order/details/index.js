import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import _ from 'lodash'
import Centrifuge from 'centrifuge'
import { Provider } from 'react-redux'

import './details.scss'

import { accountSlice } from '../../entities/account/reduxSlice'
import { guestSlice } from '../../entities/guest/reduxSlice'
import { buildGuestInitialState } from '../../entities/guest/utils'
import { addOrderEvent, orderSlice } from '../../entities/order/reduxSlice'
import { createStoreFromPreloadedState } from './redux/store'
import OrderTimeline from './Timeline'
import storage from '../../search/address-storage'

const timelineEl = document.getElementById('order-timeline')
const checkoutResetEl = document.getElementById('checkout-reset')

const isSameOrder = (order, event) => order.id === event.order.id

if (timelineEl) {
  const order = JSON.parse(timelineEl.dataset.order)
  const orderNodeId = order['@id']
  const orderAccessToken = timelineEl.dataset.orderAccessToken

  const buildInitialState = () => {
    return {
      [accountSlice.name]: accountSlice.getInitialState(),
      [guestSlice.name]: buildGuestInitialState(orderNodeId, orderAccessToken),
      [orderSlice.name]: {
        ...orderSlice.getInitialState(),
        ...order,
      },
    }
  }
  const store = createStoreFromPreloadedState(buildInitialState())

  const options = {
    centrifugo: {
      channel: timelineEl.dataset.centrifugoChannel,
      token: timelineEl.dataset.centrifugoToken,
    },
  }

  const root = createRoot(timelineEl)
  root.render(
    <StrictMode>
      <Provider store={store}>
        <OrderTimeline order={order} />
      </Provider>
    </StrictMode>,
  )

  const handleEvent = (name, event) => {
    if (!isSameOrder(order, event)) {
      return
    }

    store.dispatch(
      addOrderEvent({
        type: name,
        createdAt: event.createdAt,
      }),
    )
  }

  if (!_.includes(['cancelled', 'fulfilled', 'refused'], order.state)) {
    const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws'

    const centrifuge = new Centrifuge(
      `${protocol}://${window.location.host}/centrifugo/connection/websocket`,
      {
        // In this case, we don't refresh the connection
        // https://github.com/centrifugal/centrifuge-js#refreshendpoint
        refreshAttempts: 0,
        onRefresh: function (ctx, cb) {
          cb({ status: 403 })
        },
      },
    )

    centrifuge.setToken(options.centrifugo.token)
    centrifuge.subscribe(options.centrifugo.channel, message => {
      const { event } = message.data

      switch (event.name) {
        case 'order:accepted':
          handleEvent('order:accepted', event.data)
          break
        case 'order:refused':
          handleEvent('order:refused', event.data)
          break
        case 'order:picked':
          handleEvent('order:picked', event.data)
          break
        case 'order:dropped':
          handleEvent('order:dropped', event.data)
          break
        case 'order:cancelled':
          handleEvent('order:cancelled', event.data)
          break
        case 'order:fulfilled':
          handleEvent('order:fulfilled', event.data)
          break
      }
    })
    centrifuge.connect()
  }
}

if (checkoutResetEl) {
  storage.remove('search_address')
  storage.remove('search_geohash')
}
