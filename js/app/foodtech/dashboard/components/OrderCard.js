import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import moment from 'moment'

import { setCurrentOrder, orderEventReceived } from '../redux/actions'

const hostname = window.location.hostname,
      socket = io('//' + hostname, { path: '/tracking/socket.io' })

class OrderCard extends React.Component {

  componentDidMount() {
    socket.on(`order:${this.props.order.id}:events`, event => {
      this.props.orderEventReceived(this.props.order, event)
    })
  }

  render() {

    const { order } = this.props

    return (
      <div className="panel panel-default FoodtechDashboard__OrderCard" onClick={ () => this.props.setCurrentOrder(order) }>
        <div className="panel-heading">
          <span>Order { order.number } (#{ order.id })</span>
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

function mapStateToProps(state) {
  return {}
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    orderEventReceived: (order, event) => dispatch(orderEventReceived(order, event)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(OrderCard))
