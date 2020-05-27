import React from 'react'
import { withTranslation } from 'react-i18next'

import OrderNumber from './OrderNumber'
import ShippingTimeRange from '../../../components/ShippingTimeRange'

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
          <li><i className="fa fa-cutlery"></i> { order.restaurant.name }</li>
          <li><i className="fa fa-user"></i> { order.customer.username }</li>
          <li><i className="fa fa-money"></i> { (order.total / 100).formatMoney() }</li>
        </ul>
      </div>
    </div>
  )
})
