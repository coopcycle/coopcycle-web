import React from 'react'
import { withTranslation } from 'react-i18next'

import OrderNumber from './OrderNumber'
import Customer from './Customer'
import ShippingTimeRange from '../../../components/ShippingTimeRange'
import Avatar from '../../../components/Avatar'
import PaymentMethodIcon from '../../../components/Payment/PaymentMethodIcon'

export default withTranslation()(({ order, onClick }) => {

  return (
    <div className="panel panel-default FoodtechDashboard__OrderCard" onClick={ () => onClick(order) }>
      <div className="panel-heading">
        <OrderNumber order={ order } />
        <span className="pull-right">
          <i className="fa fa-clock-o mr-2"></i>
          <small>
            <ShippingTimeRange value={ order.shippingTimeRange } short />
          </small>
        </span>
      </div>
      <div className="panel-body">
        <ul className="list-unstyled">
          <li><i className="fa fa-cutlery"></i> { order.vendor.name }</li>
          <li>
            <Customer customer={ order.customer } />
            { order.notes && <i className="fa fa-comments ml-4"></i> }
          </li>
          <li><i className="fa fa-money"></i> { (order.total / 100).formatMoney() }</li>
          <li><PaymentMethodIcon code={ order.paymentMethod } height="18" /></li>
          { order.assignedTo && (
            <li>
              <Avatar username={ order.assignedTo } />
              <span className="ml-2">{ order.assignedTo }</span>
            </li>
          ) }
        </ul>
      </div>
    </div>
  )
})
