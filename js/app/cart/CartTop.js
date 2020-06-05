import React from 'react'

class CartTop extends React.Component
{
  constructor(props) {
    super(props)
    this.state = {
      total: props.total,
      itemsTotal: props.itemsTotal,
      restaurant: props.restaurant
    }
    this.anchorRef = React.createRef()
  }

  componentDidMount() {
    // When the component is mounted, we add a listener on the DOM element
    // The main cart (on the restaurant page) will trigger events on the DOM element
    this.anchorRef.current.addEventListener('cart:change', e => this._onCartChange(e.detail))
  }

  _onCartChange(cart) {
    const { itemsTotal, total, restaurant } = cart
    this.setState({ itemsTotal, total, restaurant })
  }

  render() {

    const { restaurant, total, itemsTotal } = this.state

    let anchorURL = '#'
    if (restaurant) {
      anchorURL = window.Routing.generate('restaurant', { id: restaurant.id })
    }

    const amount = itemsTotal > 0 ? total : itemsTotal

    return (
      <a ref={ this.anchorRef } href={ anchorURL } data-cart-listener>
        <i className="fa fa-shopping-basket mr-2" aria-hidden="true"></i>
        <span>{ (amount / 100).formatMoney() }</span>
      </a>
    )
  }
}

export default CartTop
