import React from 'react';

class CartItem extends React.Component
{
  render() {
    return (
      <li className="list-group-item">
        <span>{this.props.name} </span>
        <span className="text-muted">x {this.props.quantity}</span>
        <span className="pull-right">{this.props.price * this.props.quantity} €</span>
      </li>
    );
  }
}

module.exports = CartItem;