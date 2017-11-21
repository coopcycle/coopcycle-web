import React from 'react';

class CartItem extends React.Component
{
  render() {
    let name = this.props.name;
    let modifiersDescription = this.props.modifiersDescription;
    if (name.length > 24) {
      name = name.substring(0, 23) + '…'
    }
    let total = (this.props.total).toFixed(2);
    return (
      <li className="list-group-item">
        <span>{name}</span>
        <span className="text-muted"> x {this.props.quantity}</span>
        <button type="button" className="close pull-right" aria-label="Close" onClick={(e) => this.props.cart.removeItem(this)}>
          <span aria-hidden="true">×</span>
        </button>
        <span className="pull-right">{total} €</span>
        { modifiersDescription ? <p><span>{modifiersDescription}</span></p> : null }
      </li>
    );
  }
}

module.exports = CartItem;
