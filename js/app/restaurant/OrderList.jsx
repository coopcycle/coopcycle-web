import React from 'react'
import OrderLabel from '../order/Label.jsx'
import _ from 'lodash'
import moment from 'moment'
import numeral  from 'numeral'
import 'numeral/locales'

const locale = $('html').attr('lang')
numeral.locale(locale)

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
              <th className="text-right">Commande</th>
              <th className="text-right">Livraison</th>
              <th></th>
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
    if (this.state.active && this.state.active['@id'] === order['@id']) {
      className = 'active'
    }

    return (
      <tr key={ order['@id'] } onClick={ () => this.props.onOrderClick(order) } style={{ cursor: 'pointer' }} className={ className }>
        <td>{ order['@id'].replace('/api/orders/', '') }</td>
        <td><OrderLabel order={ order } /></td>
        <td><i className="fa fa-clock-o" aria-hidden="true"></i>  { moment(order.preparationDate).format('lll') }</td>
        <td className="text-right">{ numeral(order.totalIncludingTax).format('0,0.00 $') }</td>
        <td className="text-right">{ numeral(order.delivery.totalIncludingTax).format('0,0.00 $') }</td>
        <td className="text-right">{ order.customer.username }</td>
      </tr>
    )
  }

  render() {

    const { orders } = this.state

    if (orders.length === 0) {
      return (
        <div className="alert alert-warning">{ this.props.i18n['No orders yet'] }</div>
      )
    }

    const preparationDate = order => moment(order.preparationDate).format('YYYY-MM-DD')
    const ordersByDate = _.mapValues(_.groupBy(orders, preparationDate), orders => {
      orders.sort((a, b) => {
        const dateA = moment(a.preparationDate);
        const dateB = moment(b.preparationDate);
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
