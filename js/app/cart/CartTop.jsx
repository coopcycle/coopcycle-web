import React from 'react'
import { findDOMNode } from 'react-dom'
import i18n from '../i18n'


class CartTop extends React.Component
{
  constructor(props) {
    super(props);
    this.state = {
      total: props.total,
      itemsTotal: props.itemsTotal,
      restaurant: props.restaurant
    }
  }

  componentDidMount() {
    // When the component is mounted, we add a listener on the DOM element
    // The main cart (on the restaurant page) will trigger events on the DOM element
    findDOMNode(this).addEventListener('cart:change', e => this._onCartChange(e.detail))
  }

  _onCartChange(cart) {
    const { itemsTotal, total, restaurant } = cart
    this.setState({ itemsTotal, total, restaurant })
  }

  render() {

    const { restaurantURL } = this.props
    const { restaurant, total, itemsTotal } = this.state

    let anchorURL = '#'
    if (restaurant) {
      anchorURL = restaurantURL.replace('__RESTAURANT_ID__', restaurant.id)
    }

    const amount = itemsTotal > 0 ? total : itemsTotal

    return (
      <a href={ anchorURL } className="btn btn-default" data-cart-listener>
        { i18n.t('CART_TITLE') } <span className="glyphicon glyphicon-shopping-cart" aria-hidden="true"></span>  { (amount / 100).formatMoney() }
      </a>
    );
  }
}

export default CartTop
