import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'

import { setCurrentOrder } from '../redux/actions'

class OrderCard extends React.Component {

  render() {

    const { order } = this.props

    return (
      <div className="panel panel-default FoodtechDashboard__OrderCard" onClick={ () => this.props.setCurrentOrder(order) }>
        <div className="panel-heading">
          <span>{ this.props.t('RESTAURANT_DASHBOARD_ORDER_TITLE', { number: order.number, id: order.id }) }</span>
          <span className="pull-right"><i className="fa fa-clock-o"></i> { moment(order.shippedAt).format('LT') }</span>
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
