import React from 'react'
import { render } from 'react-dom'
import classNames from 'classnames'

import { asText } from '../components/ShippingTimeRange'

const FulfillmentBadge = ({ delivery }) => {

  return (
    <span className="restaurant-item__time-range">
      <i className="fa fa-clock-o mr-2"></i>
      <span>{ asText(delivery.range) }</span>
    </span>
  )
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery) {
      return
    }

    if (!data.delivery.range) {
      return
    }

    render(<FulfillmentBadge { ...data } />, el)
  })
})
