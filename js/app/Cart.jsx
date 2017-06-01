import React from 'react';
import _ from 'underscore';
import CartItem from './CartItem.jsx';

class Cart extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      items: props.items,
      date: props.date
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
      product: id,
      date: this.state.date
    }).then((cart) => {
      this.setState({items: cart.items});
    });
  }
  onDateChange(dateString) {
    $.post(this.props.addToCartURL, {
      date: dateString
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

    var cartContent;
    if (items.length > 0) {
      cartContent = (
        <div className="list-group">{items}</div>
      );
    } else {
      cartContent = (
        <div className="alert alert-warning">Votre panier est vide</div>
      );
    }

    var sum = _.reduce(this.state.items, function(memo, item) {
      return memo + (item.price * item.quantity);
    }, 0).toFixed(2);

    var btnClasses = ['btn', 'btn-block', 'btn-primary'];
    if (items.length === 0) {
      btnClasses.push('disabled');
    }

    return (
      <div className="cart">
        {cartContent}
        <strong>Total {sum} €</strong>
        <hr />
        <a href={this.props.validateCartURL} className={btnClasses.join(' ')}>Commander</a>
      </div>
    );
  }
}

module.exports = Cart;