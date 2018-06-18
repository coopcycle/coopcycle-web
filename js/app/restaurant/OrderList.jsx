import React from 'react'
import OrderLabel from '../order/Label.jsx'
import _ from 'lodash'
import moment from 'moment'

class OrderList extends React.Component
{
  constructor(props) {
    super(props)
    this.state = {
      orders: props.orders,
      active: props.active
    }
  }

  setActive(order) {
    this.setState({ active: order })
  }

  addOrder(order) {

    let { orders } = this.state
    orders.push(order)

    this.setState({ orders })
  }

  renderOrders(date, orders) {
    return (
      <div key={ date }>
        <h4>{ _.startCase(moment(date).calendar().split(' ')[0]) }</h4>
        { this.renderOrdersTable(orders) }
      </div>
    )
  }

  renderOrdersTable(orders) {
    return (
      <div>
        <table className="table table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>État</th>
              <th>Date de préparation</th>
              <th></th>
              <th className="text-right">Total</th>
            </tr>
          </thead>
          <tbody>
          { _.map(orders, (order) => this.renderOrderRow(order)) }
          </tbody>
        </table>
      </div>
    )
  }

  renderOrderRow(order) {

    let className = ''
    if (this.state.active && this.state.active['id'] === order['id']) {
      className = 'active'
    }

    return (
      <tr key={ order['id'] } onClick={ () => this.props.onOrderClick(order) } style={{ cursor: 'pointer' }} className={ className }>
        <td>{ order.id }</td>
        <td><OrderLabel order={ order } /></td>
        <td><i className="fa fa-clock-o" aria-hidden="true"></i>  { moment(order.shippedAt).format('lll') }</td>
        <td>{ `${order.items.length} plats` }</td>
        <td className="text-right">{ (order.total / 100).formatMoney() }</td>
      </tr>
    )
  }

  render() {

    const { orders } = this.state

    if (orders.length === 0) {
      return (
        <div className="alert alert-warning">{ i18n.t('ADMIN_DASHBOARD_ORDERS_NOORDERS') }</div>
      )
    }

    const shippedAt = order => moment(order.shippedAt).format('YYYY-MM-DD')
    const ordersByDate = _.mapValues(_.groupBy(orders, shippedAt), orders => {
      orders.sort((a, b) => {
        const dateA = moment(a.shippedAt);
        const dateB = moment(b.shippedAt);
        if (dateA === dateB) {
          return 0;
        }

        return dateA.isAfter(dateB) ? 1 : -1;
      });

      return orders
    })

    return (
      <div>
      { _.map(ordersByDate, (orders, date) => this.renderOrders(date, orders)) }
      </div>
    )
  }
}

module.exports = OrderList;
