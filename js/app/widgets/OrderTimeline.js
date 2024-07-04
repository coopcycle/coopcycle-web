import React from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import Centrifuge from 'centrifuge'

import OrderTimeline from '../order/Timeline'

const isSameOrder = (order, event) => order.id === event.order.id

function handleEvent(name, event, order, timeline) {
  if (!isSameOrder(order, event)) {

    return
  }

  timeline.addEvent({
    name,
    createdAt: event.createdAt
  })
}

export default function(el, options) {

  const timeline = render(<OrderTimeline order={ options.order } events={ options.events } />, el)

  if (!_.includes(['cancelled', 'fulfilled', 'refused'], options.order.state)) {

    const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'

    const centrifuge = new Centrifuge(`${protocol}://${window.location.host}/centrifugo/connection/websocket`, {
      // In this case, we don't refresh the connection
      // https://github.com/centrifugal/centrifuge-js#refreshendpoint
      refreshAttempts: 0,
      onRefresh: function(ctx, cb) {
        cb({ status: 403 })
      }
    })
    centrifuge.setToken(options.centrifugo.token)
    centrifuge.subscribe(options.centrifugo.channel, message => {
      const { event } = message.data

      switch (event.name) {
        case 'order:accepted':
          handleEvent('order:accepted', event.data, options.order, timeline)
          break
        case 'order:refused':
          handleEvent('order:refused', event.data, options.order, timeline)
          break
        case 'order:picked':
          handleEvent('order:picked', event.data, options.order, timeline)
          break
        case 'order:dropped':
          handleEvent('order:dropped', event.data, options.order, timeline)
          break
        case 'order:cancelled':
          handleEvent('order:cancelled', event.data, options.order, timeline)
          break
        case 'order:fulfilled':
          handleEvent('order:fulfilled', event.data, options.order, timeline)
          break
      }
    })
    centrifuge.connect()

  }
}
