import React from 'react'
import ShippingTimeRange from '../ShippingTimeRange'

export default function TimeRange({ timeRange }) {
  return (<span className="text-success">
            <i className="fa fa-clock-o fa-lg mr-2"></i>
            <strong data-testid="order.time">
              <ShippingTimeRange value={ timeRange } />
            </strong>
          </span>)
}
