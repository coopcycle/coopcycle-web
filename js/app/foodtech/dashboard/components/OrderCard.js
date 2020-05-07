import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'

import { setCurrentOrder } from '../redux/actions'
import ShippingTimeRange from '../../../components/ShippingTimeRange'

class OrderCard extends React.Component {

  render() {

    const { order } = this.props

    return (
      <div className="panel panel-default FoodtechDashboard__OrderCard" onClick={ () => this.props.setCurrentOrder(order) }>
        <div className="panel-heading">
          <span className="order-number">
            { this.props.t('RESTAURANT_DASHBOARD_ORDER_TITLE', { number: order.number }) }
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
  }

}

function mapStateToProps() {
  return {}
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(OrderCard))
