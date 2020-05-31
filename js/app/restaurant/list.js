import React from 'react'
import { render } from 'react-dom'

import { asText } from '../components/ShippingTimeRange'

const FulfillmentBadge = ({ range }) => {

  return (
    <span className="restaurant-item__time-range">
      <i className="fa fa-clock-o mr-2"></i>
      <span>{ asText(range) }</span>
    </span>
  )
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery && !data.collection) {
      return
    }

    if (data.delivery && data.delivery.range) {
      render(<FulfillmentBadge range={ data.delivery.range } />, el)
      return
    }

    if (data.collection && data.collection.range) {
      render(<FulfillmentBadge range={ data.collection.range } />, el)
    }
  })
})
