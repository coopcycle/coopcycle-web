import React from 'react';
import DeliveryListItem from './DeliveryListItem.jsx';
import _ from 'underscore'
import moment from 'moment'

class OrderList extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: props.deliveries || [],
      active: null
    };
  }
  addItem(delivery) {
    let { items } = this.state
    items = items.slice()
    items.push(delivery)

    this.setState({ items })
  }
  removeItemById(id) {
    let { items } = this.state
    items = items.slice()
    items = _.filter(items, item => item.id !== id)
    this.setState({ items })
  }
  updateStatusById(id, status) {
    let { items } = this.state
    items = items.slice()
    items = items.map(item => {
      if (item.id === id) {
        return {
          ...item,
          status,
        }
      }

      return item
    })

    this.setState({ items })
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

    let { items } = this.state

    items.sort((a, b) => moment(a.date).isBefore(moment(b.date)) ? -1 : 1)

    items = _.map(items, (item, key) => {
      return (
        <DeliveryListItem
          key={key}
          color={ '#fff' }
          status={ item.status }
          id={ item.id }
          courier={ item.courier }
          date={ item.date }
          originAddress={ item.originAddress }
          deliveryAddress={ item.deliveryAddress }
          onClick={this.onItemClick.bind(this, item)} />
      );
    });

    return (
      <div className="list-group">
        { items }
      </div>
    );
  }
}

module.exports = OrderList;
