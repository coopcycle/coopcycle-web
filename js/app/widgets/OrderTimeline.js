import React from 'react'
import { render } from 'react-dom'
import _ from 'lodash'

import OrderTimeline from '../order/Timeline'

export default (el, options) => {

  const timeline = render(<OrderTimeline order={ options.order } events={ options.events } />, el)

  if (!_.includes(['cancelled', 'fulfilled', 'refused'], options.order.state)) {
    const socket = io('//' + window.location.hostname, { path: '/tracking/socket.io' })
    socket.on(`order:${options.order.id}:events`, event => timeline.addEvent(event))
  }

}
