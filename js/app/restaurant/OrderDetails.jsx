import React from 'react'
import OrderLabel from '../order/Label.jsx'
import moment from 'moment'

import i18n from '../i18n'

const locale = $('html').attr('lang')

moment.locale(locale)

const hasAdjustments = (item) => item.adjustments.hasOwnProperty('menu_item_modifier') && item.adjustments['menu_item_modifier'].length > 0

class OrderList extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      order: props.order
    }
  }

  resolveOrderRoute(route) {

    const restaurant = this.props.restaurant
    const order = this.state.order

    return this.props.routes[route]
      .replace('__RESTAURANT_ID__', restaurant.id)
      .replace('__ORDER_ID__', order.id)
  }

  resolveUserRoute(route) {
    const customer = this.state.order.customer

    return this.props.routes[route].replace('__USERNAME__', customer.username)
  }

  renderWaitingButtons() {
    return (
      <div className="row">
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_refuse') }>
            <button type="submit" className="btn btn-block btn-sm btn-danger">
              <i className="fa fa-ban" aria-hidden="true"></i>  { i18n.t('ADMIN_DASHBOARD_ORDERS_REFUSE') }
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_accept') }>
            <button type="submit" className="btn btn-block btn-sm btn-success">
              <i className="fa fa-check" aria-hidden="true"></i>  { i18n.t('ADMIN_DASHBOARD_ORDERS_ACCEPT') }
            </button>
          </form>
        </div>
      </div>
    )
  }

  renderAcceptedButtons() {
    return (
      <div className="row">
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_cancel') }>
            <button type="submit" className="btn btn-block btn-sm btn-danger">
              <i className="fa fa-ban" aria-hidden="true"></i>  { i18n.t('ADMIN_DASHBOARD_ORDERS_CANCEL') }
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_delay') }>
            <button type="submit" className="btn btn-block btn-sm btn-primary">
              <i className="fa fa-clock-o" aria-hidden="true"></i>  { i18n.t('ADMIN_DASHBOARD_ORDERS_DELAY') }
            </button>
          </form>
        </div>
      </div>
    )
  }

  setOrder(order) {
    this.setState({ order })
  }

  renderOrderItems() {

    return (
      <table className="table table-condensed">
        <tbody>
          { this.state.order.items.map((item, key) =>
            <tr key={ key }>
              <td>
                <span>{ item.quantity } x { item.name }</span>
                { hasAdjustments(item) && ( <br /> ) }
                { hasAdjustments(item) && this.renderOrderItemAdjustments(item) }
              </td>
              <td className="text-right">{ (item.total / 100).formatMoney(2, window.AppData.currencySymbol) }</td>
            </tr>
          ) }
        </tbody>
      </table>
    )
  }

  renderOrderItemAdjustments(item) {
    return (
      <ul className="list-unstyled">
        { item.adjustments['menu_item_modifier'].map((adjustment) =>
          <li key={ adjustment.id }>
            <small className="text-muted">{ adjustment.label }</small>
          </li>
        ) }
      </ul>
    )
  }

  renderOrderTotal() {
    return (
      <table className="table">
        <tbody>
          <tr>
            <td><strong>Total TTC</strong></td>
            <td className="text-right"><strong>{ (this.state.order.total / 100).formatMoney(2, window.AppData.currencySymbol) }</strong></td>
          </tr>
          <tr>
            <td><strong>Dont TVA</strong></td>
            <td className="text-right"><strong>{ (this.state.order.taxTotal / 100).formatMoney(2, window.AppData.currencySymbol) }</strong></td>
          </tr>
        </tbody>
      </table>
    )
  }

  render() {

    const { order } = this.state

    if (!order) {
      return (
        <div className="restaurant-dashboard__details__container restaurant-dashboard__details__container--empty">
          <div>
            { i18n.t('ADMIN_DASHBOARD_CLICK_FOR_DETAILS') }
          </div>
        </div>
      )
    }

    return (
      <div className="restaurant-dashboard__details__container">
        <h4>
          <span>{ i18n.t('ADMIN_DASHBOARD_ORDERS_ORDER') } #{ order.id }</span>
          <button type="button" className="close" onClick={ () => this.props.onClose() }>
            <span aria-hidden="true">&times;</span>
          </button>
        </h4>
        <div className="restaurant-dashboard__details__user">
          <p>
            <span className="text-left"><OrderLabel order={ order } /></span>
            <strong className="pull-right text-success">
              { moment(order.preparationExpectedAt).format('LT') }  <i className="fa fa-clock-o" aria-hidden="true"></i>
            </strong>
          </p>
          <p className="text-right">
            <i className="fa fa-user" aria-hidden="true"></i> { order.customer.givenName } { order.customer.familyName }
          </p>
          { order.customer.telephone &&
          <p className="text-right"><i className="fa fa-phone" aria-hidden="true"></i> { order.customer.telephone }</p> }
          <p className="text-right">
            <a href={ this.resolveUserRoute('user_details') }>
              <i className="fa fa-external-link" aria-hidden="true"></i> { order.customer.username } 
            </a>
          </p>
        </div>
        <h4>{ i18n.t('ADMIN_DASHBOARD_ORDERS_DISHES') }</h4>
        <div className="restaurant-dashboard__details__dishes">
          { this.renderOrderItems() }
        </div>
        <div className="restaurant-dashboard__details__total">
          { this.renderOrderTotal() }
        </div>
        <div className="restaurant-dashboard__details__buttons">
          { order.state === 'new' && this.renderWaitingButtons() }
          { order.state === 'accepted' && this.renderAcceptedButtons() }
        </div>
      </div>
    )
  }
}

export default OrderList
