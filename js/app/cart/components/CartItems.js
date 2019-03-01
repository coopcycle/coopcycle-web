import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'

import CartItem from './CartItem'
import { removeItem } from '../redux/actions'

class CartItems extends React.Component {

  render() {

    if (this.props.items.length === 0) {
      return (
        <div className="alert alert-warning">{ this.props.t("CART_EMPTY") }</div>
      )
    }

    return (
      <div className="cart__items">
        { this.props.items.map((item, key) => (
          <CartItem
            key={ key }
            id={ item.id }
            name={ item.name }
            total={ item.total }
            quantity={ item.quantity }
            adjustments={ item.adjustments }
            onClickRemove={ () => this.props.removeItem(item.id) } />
        )) }
      </div>
    )
  }

}

function mapStateToProps (state) {

  const { cart, restaurant } = state

  let items = cart.items
  if (cart.restaurant.id !== restaurant.id) {
    items = []
  }

  return {
    items,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeItem: itemID => dispatch(removeItem(itemID)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(CartItems))
