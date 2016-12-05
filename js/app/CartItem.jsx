import React from 'react';

class CartItem extends React.Component
{
  render() {
    return (
      <li className="list-group-item">
        <span>{this.props.name} </span>
        <span className="text-muted">x {this.props.quantity}</span>
        <button type="button" className="close pull-right" aria-label="Close" onClick={(e) => this.props.cart.removeItem(this)}>
          <span aria-hidden="true">×</span>
        </button>
        <span className="pull-right">{this.props.price * this.props.quantity} €</span>
      </li>
    );
  }
}

module.exports = CartItem;