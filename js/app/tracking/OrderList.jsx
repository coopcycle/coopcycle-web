import React from 'react';
import OrderListItem from './OrderListItem.jsx';
import _ from 'underscore';

class OrderList extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: {},
      active: null
    };
  }
  setItem(key, order) {
    let items = this.state.items;

    if (items[key]) {
      items[key].state = order.state;
    } else {
      items[key] = order;
    }

    this.setState({items});
  }
  removeItem(key) {
    let items = this.state.items;
    if (items[key]) {
      delete items[key];
      this.setState({items});
    }
  }
  onItemClick(order) {
    this.setState({active: order.key});
    this.props.onItemClick(order);
  }
  onItemClose() {
    this.setState({active: null});
    this.props.onReset();
  }
  render() {
    var items = _.map(this.state.items, (item, key) => {
      return (
        <OrderListItem
          key={key}
          id={key}
          color={item.color}
          state={item.state}
          order={item}
          active={this.state.active === key}
          onClick={this.onItemClick.bind(this, item)}
          onClose={this.onItemClose.bind(this)} />
      );
    });

    return (
      <ul className="list-unstyled">
        {items}
      </ul>
    );
  }
}

module.exports = OrderList;