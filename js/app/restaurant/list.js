import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'

import { asText } from '../components/ShippingTimeRange'

import './list.scss'

const FulfillmentBadge = ({ range }) => {

  return (
    <span className="restaurant-item__time-range">
      <i className="fa fa-clock-o mr-2"></i>
      <span>{ asText(range, false, true) }</span>
    </span>
  )
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery && !data.collection) {
      return
    }

    const ranges = []
    if (data.delivery && data.delivery.range) {
      ranges.push(data.delivery.range)
    }
    if (data.collection && data.collection.range) {
      ranges.push(data.collection.range)
    }

    ranges.sort((a, b) => moment(a[0]).isSame(b[0]) ? 0 : (moment(a[0]).isBefore(b[0]) ? -1 : 1))

    render(<FulfillmentBadge range={ ranges[0] } />, el)
  })
})
