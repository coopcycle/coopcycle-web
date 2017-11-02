import React from 'react';
import moment from 'moment';
import OrderLabel from '../order/Label.jsx';

moment.locale($('html').attr('lang'));

const routes = window.__routes;

class OrderListItem extends React.Component
{
  resolveOrderRoute(route) {
    const order = this.props.order;
    const id = order['@id'].replace('/api/orders/', '');

    return routes[route].replace('__ORDER_ID__', id)
  }

  resolveUserRoute(route) {
    const customer = this.props.order.customer;

    return routes[route].replace('__USERNAME__', customer.username)
  }

  renderWaitingButtons() {
    return (
      <div className="row">
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_refuse') }>
            <button type="submit" className="btn btn-block btn-sm btn-danger">
              <i className="fa fa-ban" aria-hidden="true"></i>  Refuser
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_accept') }>
            <button type="submit" className="btn btn-block btn-sm btn-success">
              <i className="fa fa-check" aria-hidden="true"></i>  Accepter
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
              <i className="fa fa-ban" aria-hidden="true"></i>  Annuler
            </button>
          </form>
        </div>
        <div className="col-sm-6">
          <form method="post" action={ this.resolveOrderRoute('order_ready') }>
            <button type="submit" className="btn btn-block btn-sm btn-success">
              <i className="fa fa-check" aria-hidden="true"></i>  Prête !
            </button>
          </form>
        </div>
      </div>
    )
  }

  render() {

    const order = this.props.order;
    const id = order['@id'].replace('/api/orders/', '');

    let classNames = [
      'panel',
      'panel-default'
    ];

    if (order.status === 'ACCEPTED') {
      classNames.push('panel-success')
    }

    return (
      <div className={ classNames.join(' ') }>
        <div className="panel-heading">
          <h3 className="panel-title"><a href={ this.resolveOrderRoute('order_details') }>Commande #{ id }</a></h3>
        </div>
        <div className="panel-body">
          <p className="text-right"><OrderLabel order={ order } /></p>
          <p className="text-right">
            <a href={ this.resolveUserRoute('user_details') }>
            { order.customer.username }  <i className="fa fa-user" aria-hidden="true"></i>
            </a>
          </p>
          { order.customer.telephone &&
          <p className="text-right">{ order.customer.telephone }  <i className="fa fa-phone" aria-hidden="true"></i></p> }
          <p className="text-right">{ moment(order.createdAt).fromNow() }  <i className="fa fa-calendar" aria-hidden="true"></i></p>

          <table className="table table-condensed">
            <tbody>
              { order.orderedItem.map((item, key) =>
                <tr key={ key }>
                  <td>{ item.quantity } x { item.name }</td>
                  <td className="text-right">{ item.quantity * item.price } €</td>
                </tr>
              ) }
              <tr>
                <td><strong>Total</strong></td>
                <td className="text-right"><strong>{ order.total } €</strong></td>
              </tr>
            </tbody>
          </table>

          <p className="text-right">
            <i className="fa fa-clock-o" aria-hidden="true"></i>  { moment(order.delivery.date).format('lll') }
          </p>

          { order.status === 'WAITING' && this.renderWaitingButtons() }
          { order.status === 'ACCEPTED' && this.renderAcceptedButtons() }

        </div>
      </div>
    );
  }
}

module.exports = OrderListItem;
