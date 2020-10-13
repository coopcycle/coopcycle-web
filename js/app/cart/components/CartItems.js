import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import CartItem from './CartItem'
import { removeItem, updateItemQuantity } from '../redux/actions'
import { selectItems, selectShowPricesTaxExcluded } from '../redux/selectors'

class CartItems extends React.Component {

  _onChangeQuantity(itemID, quantity) {
    if (!_.isNumber(quantity)) {
      return
    }

    if (quantity === 0) {
      this.props.removeItem(itemID)
      return
    }

    this.props.updateItemQuantity(itemID, quantity)
  }

  _onRemoveItem(itemID) {
    this.props.removeItem(itemID)
  }

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
            showPricesTaxExcluded={ this.props.showPricesTaxExcluded }
            onChangeQuantity={ quantity => this._onChangeQuantity(item.id, quantity) }
            onClickRemove={ () => this._onRemoveItem(item.id) } />
        )) }
      </div>
    )
  }

}

function mapStateToProps (state) {

  const items = selectItems(state)

  // Make sure items are always in the same order
  // We order them by id asc
  items.sort((a, b) => a.id - b.id)

  return {
    items,
    showPricesTaxExcluded: selectShowPricesTaxExcluded(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeItem: itemID => dispatch(removeItem(itemID)),
    updateItemQuantity: (itemID, quantity) => dispatch(updateItemQuantity(itemID, quantity)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(CartItems))
