import React from 'react'
import { withTranslation } from 'react-i18next'

import ShippingTimeRange from '../../../components/ShippingTimeRange'

export default withTranslation()(({ order, onClick, t }) => {

  return (
    <div className="panel panel-default FoodtechDashboard__OrderCard" onClick={ () => onClick(order) }>
      <div className="panel-heading">
        <span className="mr-2">
          { !order.takeaway && (<i className="fa fa-bicycle"></i>) }
          { order.takeaway && (<i className="fa fa-cube"></i>) }
        </span>
        <span className="order-number">
          { t('RESTAURANT_DASHBOARD_ORDER_TITLE', { number: order.number }) }
        </span>
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
          <li><i className="fa fa-money"></i> { (order.total / 100).formatMoney(2, window.AppData.currencySymbol) }</li>
        </ul>
      </div>
    </div>
  )
})
