import React from 'react'
import { withTranslation } from 'react-i18next'
import { Timeline } from 'antd'
import moment from 'moment'

export default withTranslation()(({ order, t }) => {

  return (
    <Timeline>
      <Timeline.Item dot={<i className="fa fa-cutlery"></i>}>
        <div>
          <strong>{ t('RESTAURANT_DASHBOARD_PREPARATION_AT', { time: moment(order.preparationExpectedAt).format('LT') }) }</strong>
        </div>
        { order.reusablePackagingEnabled && (
          <span className="text-warning">{ t('ADMIN_DASHBOARD_ORDERS_PACKAGING_ALERT') }</span>
        )}
      </Timeline.Item>
      <Timeline.Item dot={<i className="fa fa-cube"></i>}>
        <div>
          <strong>{ t('RESTAURANT_DASHBOARD_PICKUP_AT', { time: moment(order.pickupExpectedAt).format('LT') }) }</strong>
        </div>
        <ul className="list-unstyled">
          <li>
            <span>{ order.restaurant.address.streetAddress }</span>
          </li>
          { order.takeaway && (
          <li>
            <span className="text-warning">{ t('ADMIN_DASHBOARD_ORDERS_TAKEAWAY_ALERT') }</span>
          </li>
          )}
        </ul>
      </Timeline.Item>
      { !order.takeaway && (
      <Timeline.Item dot={<i className="fa fa-arrow-down"></i>}>
        <div>
          <strong>{ t('RESTAURANT_DASHBOARD_DROPOFF_AT', { time: moment(order.shippedAt).format('LT') }) }</strong>
        </div>
        <ul className="list-unstyled">
          <li>{ order.shippingAddress.streetAddress }</li>
        </ul>
        { order.shippingAddress.description && (
          <div className="speech-bubble">
            <i className="fa fa-quote-left"></i>  { order.shippingAddress.description }
          </div>
        ) }
      </Timeline.Item>
      )}
    </Timeline>
  )
})
