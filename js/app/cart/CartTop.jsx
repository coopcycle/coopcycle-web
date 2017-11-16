import React from 'react';

class CartTop extends React.Component
{
  render() {
    return (
      <a href={this.props.validateCartURL} className="btn btn-default navbar-btn navbar-right">
        Panier: <span className="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span>  {this.props.total} €
      </a>
    );
  }
}

export default CartTop
