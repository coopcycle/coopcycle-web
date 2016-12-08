import React from 'react';
import _ from 'underscore';
import CartItem from './CartItem.jsx';

class Cart extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: props.items
    };
  }
  removeItem(item) {
    let removeFromCartURL = this.props.removeFromCartURL.replace('__PRODUCT__', item.props.id);
    $.ajax({
      url: removeFromCartURL,
      type: 'DELETE'
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }
  addProductById(id) {
    $.post(this.props.addToCartURL, {
      product: id
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }
  render() {
    var items = this.state.items.map((item, key) => {
      return (
        <CartItem
          cart={this}
          id={item.id}
          key={key}
          name={item.name}
          price={item.price}
          quantity={item.quantity} />
      );
    });
    var sum = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.price * item.quantity);
    }, 0).toFixed(2);
    return (
      <div className="cart">
        <div className="list-group">
        {items}
        </div>
        <strong>Total {sum} €</strong>
      </div>
    );
  }
}

module.exports = Cart;