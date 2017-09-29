import React from 'react';
import OrderListItem from './OrderListItem.jsx';
import _ from 'lodash';
import moment from 'moment';

class OrderList extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      orders: props.orders,
    };
  }
  addOrder(order) {
    let { orders } = this.state;
    orders.push(order);

    this.setState({ orders });
  }
  render() {

    let { orders } = this.state

    orders.sort((a, b) => {
      const dateA = moment(a.delivery.date);
      const dateB = moment(b.delivery.date);
      if (dateA === dateB) {
        return 0;
      }

      return dateA.isAfter(dateB) ? 1 : -1;
    });

    var items = _.map(orders, (order, key) => {
      return (
        <OrderListItem
          key={key}
          order={order} />
      );
    });

    return (
      <div>
        {items}
      </div>
    );
  }
}

module.exports = OrderList;
