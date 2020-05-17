import React from 'react'
import { render } from 'react-dom'
import classNames from 'classnames'

import { asText } from '../components/ShippingTimeRange'

const FulfillmentBadge = ({ delivery }) => {

  return (
    <span className={ classNames({
      'btn': true,
      'btn-sm': true,
      'btn-success': delivery.today && delivery.fast,
      'btn-warning': !(delivery.today && delivery.fast),
    }) }>
      <i className="fa fa-bicycle mr-2"></i>
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

    const container = document.createElement('span')
    render(<FulfillmentBadge { ...data } />, container)
    el.appendChild(container)
  })
})
