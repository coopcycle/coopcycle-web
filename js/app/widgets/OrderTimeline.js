import React from 'react'
import { render } from 'react-dom'
import _ from 'lodash'

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

    const socket = io(`//${window.location.hostname}`, {
      path: '/tracking/socket.io',
      transports: [ 'websocket' ],
      query: {
        token: options.jwt,
      },
    })

    socket.on('order:accepted',  event => handleEvent('order:accepted', event, options.order, timeline))
    socket.on('order:refused',   event => handleEvent('order:refused', event, options.order, timeline))
    socket.on('order:picked',    event => handleEvent('order:picked', event, options.order, timeline))
    socket.on('order:dropped',   event => handleEvent('order:dropped', event, options.order, timeline))
    socket.on('order:cancelled', event => handleEvent('order:cancelled', event, options.order, timeline))
    socket.on('order:fulfilled', event => handleEvent('order:fulfilled', event, options.order, timeline))

  }

}
