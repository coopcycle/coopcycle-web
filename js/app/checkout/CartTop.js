import React from 'react'
import axios from 'axios'

class CartTop extends React.Component
{
  constructor(props) {
    super(props)
    this.state = {
      total: 0,
      itemsTotal: 0,
    }
    this.anchorRef = React.createRef()
  }

  componentDidMount() {

    axios
      .get(this.props.url)
      .then((response) => {
        if (response.status === 200) {
          this.setState(response.data)
        }
      })
      // eslint-disable-next-line
      .catch((e) => { /* do nothing */ })

    // When the component is mounted, we add a listener on the DOM element
    // The main cart (on the restaurant page) will trigger events on the DOM element
    this.anchorRef.current.addEventListener('cart:change', e => this._onCartChange(e.detail))
  }

  _onCartChange(cart) {
    const { itemsTotal, total } = cart
    this.setState({ itemsTotal, total })
  }

  render() {

    const { total, itemsTotal } = this.state

    const anchorURL = itemsTotal > 0 ? this.props.href : '#'
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
