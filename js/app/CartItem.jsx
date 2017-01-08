import React from 'react';

class CartItem extends React.Component
{
  render() {
    let name = this.props.name;
    if (name.length > 24) {
      name = name.substring(0, 23) + '…'
    }
    let price = (this.props.price * this.props.quantity).toFixed(2);
    return (
      <li className="list-group-item">
        <span>{name} </span>
        <span className="text-muted">x {this.props.quantity}</span>
        <button type="button" className="close pull-right" aria-label="Close" onClick={(e) => this.props.cart.removeItem(this)}>
          <span aria-hidden="true">×</span>
        </button>
        <span className="pull-right">{price} €</span>
      </li>
    );
  }
}

module.exports = CartItem;