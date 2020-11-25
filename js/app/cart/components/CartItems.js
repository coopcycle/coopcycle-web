import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import CartItem from './CartItem'
import { removeItem, updateItemQuantity } from '../redux/actions'
import { selectItems, selectItemsGroups, selectShowPricesTaxExcluded } from '../redux/selectors'

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

  renderItems(items) {

    // Make sure items are always in the same order
    // We order them by id asc
    items.sort((a, b) => a.id - b.id)

    return (
      <div>
        { items.map((item, key) => (
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

  render() {

    if (this.props.items.length === 0) {
      return (
        <div className="alert alert-warning">{ this.props.t("CART_EMPTY") }</div>
      )
    }

    if (_.size(this.props.itemsGroups) > 1) {

      return (
        <div className="cart__items">
          { _.map(this.props.itemsGroups, (items, title) => {
            return (
              <React.Fragment key={ title }>
                <h5 className="text-muted">{ title }</h5>
                { this.renderItems(items) }
              </React.Fragment>
            )
          })}
        </div>
      )
    }

    return (
      <div className="cart__items">
        { this.renderItems(this.props.items) }
      </div>
    )
  }
}

function mapStateToProps (state) {

  const items = selectItems(state)
  const itemsGroups = selectItemsGroups(state)

  return {
    items,
    itemsGroups,
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
