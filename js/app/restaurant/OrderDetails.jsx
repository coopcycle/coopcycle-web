import React from 'react';
import OrderLabel from '../order/Label.jsx';
import _ from 'lodash';
import moment from 'moment';
import numeral  from 'numeral';
import 'numeral/locales'

const locale = $('html').attr('lang')

numeral.locale(locale)
moment.locale(locale)

class OrderList extends React.Component {

  constructor(props) {
    super(props);

    this.state = {
      order: props.order
    };
  }

  resolveOrderRoute(route) {
    const order = this.state.order;
    const id = order['@id'].replace('/api/orders/', '');

    return this.props.routes[route].replace('__ORDER_ID__', id)
  }

  resolveUserRoute(route) {
    const customer = this.state.order.customer;

    return this.props.routes[route].replace('__USERNAME__', customer.username)
  }

  renderWaitingButtons() {
    return (
      <div className="row">
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_refuse') }>
            <button type="submit" className="btn btn-block btn-sm btn-danger">
              <i className="fa fa-ban" aria-hidden="true"></i>  { this.props.i18n['Refuse'] }
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_accept') }>
            <button type="submit" className="btn btn-block btn-sm btn-success">
              <i className="fa fa-check" aria-hidden="true"></i>  { this.props.i18n['Accept'] }
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
              <i className="fa fa-ban" aria-hidden="true"></i>  { this.props.i18n['Cancel'] }
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_ready') }>
            <button type="submit" className="btn btn-block btn-sm btn-success">
              <i className="fa fa-check" aria-hidden="true"></i>  { this.props.i18n['Ready!'] }
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
          { this.state.order.orderedItem.map((item, key) =>
            <tr key={ key }>
              <td>{ item.quantity } x { item.name }</td>
              <td className="text-right">{ numeral(item.quantity * item.price).format('$0,0.00') }</td>
            </tr>
          ) }
        </tbody>
      </table>
    )
  }

  renderOrderTotal() {
    return (
      <table className="table">
        <tbody>
          <tr>
            <td><strong>Total</strong></td>
            <td className="text-right"><strong>{ numeral(this.state.order.total).format('$0,0.00') }</strong></td>
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
          { this.props.i18n['Click on an order to display details'] }
          </div>
        </div>
      )
    }

    return (
      <div className="restaurant-dashboard__details__container">
        <h4>
          <span>{ this.props.i18n['Order'] } #{ order['@id'].replace('/api/orders/', '') }</span>
          <button type="button" className="close" onClick={ () => this.props.onClose() }>
            <span aria-hidden="true">&times;</span>
          </button>
        </h4>
        <div className="restaurant-dashboard__details__user">
          <p>
            <span className="text-left"><OrderLabel order={ order } /></span>
            <strong className="pull-right text-success">
                { moment(order.preparationDate).format('lll') }  <i className="fa fa-clock-o" aria-hidden="true"></i>
            </strong>
          </p>
          { order.customer.telephone &&
          <p className="text-right">{ order.customer.telephone }  <i className="fa fa-phone" aria-hidden="true"></i></p> }
          <p className="text-right">
            <a href={ this.resolveUserRoute('user_details') }>
              { order.customer.username }  <i className="fa fa-user" aria-hidden="true"></i>
            </a>
          </p>
        </div>
        <h4>{ this.props.i18n['Dishes'] }</h4>
        <div className="restaurant-dashboard__details__dishes">
          { this.renderOrderItems() }
        </div>
        <div className="restaurant-dashboard__details__total">
          { this.renderOrderTotal() }
        </div>
        <div className="restaurant-dashboard__details__buttons">
          { order.status === 'WAITING' && this.renderWaitingButtons() }
          { order.status === 'ACCEPTED' && this.renderAcceptedButtons() }
        </div>
      </div>
    )
  }
}

module.exports = OrderList;
