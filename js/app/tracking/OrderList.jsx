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
    this.props.onItemClick(order);
    this.setState({key: order.key});
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
          active={this.state.key === key}
          onClick={this.props.onItemClick.bind(this, item)} />
      );
    });

    return (
      <div className="list-group">
        {items}
      </div>
    );
  }
}

module.exports = OrderList;