import React from 'react'
import { connect } from 'react-redux'

import { totalTaxExcluded } from '../../../utils/tax'
import {
  DecrementQuantityButton,
  IncrementQuantityButton,
} from '../ChangeQuantityButton'
import { removeItem, updateItemQuantity } from '../../redux/actions'
import _ from 'lodash'
import { selectShowPricesTaxExcluded } from '../../redux/selectors'

const truncateText = text => {
  if (text.length > 24) {
    return text.substring(0, 23) + '…'
  }

  return text
}

class CartItem extends React.Component {

  renderAdjustments() {
    const { adjustments } = this.props

    if (Object.prototype.hasOwnProperty.call(adjustments, 'menu_item_modifier')) {
      return (
        <>
          { adjustments.menu_item_modifier.map((adjustment, index) =>
            <div
              key={ `cart-item-${ this.props.id }-adjustment-${ index }` }
              className="d-flex align-items-center justify-content-between">
              <span>{ truncateText(adjustment.label) }</span>
              { adjustment.amount > 0 && (
                <span>{ (adjustment.amount / 100).formatMoney() }</span>
              ) }
            </div>,
          ) }
        </>
      )
    }
  }

  _onChangeQuantity(quantity) {
    if (!_.isNumber(quantity)) {
      return
    }

    if (quantity === 0) {
      this.props.removeItem(this.props.id)
      return
    }

    this.props.updateItemQuantity(this.props.id, quantity)
  }

  decrement() {
    const quantity = this.props.quantity - 1
    this._onChangeQuantity(quantity)
  }

  increment() {
    const quantity = this.props.quantity + 1
    this._onChangeQuantity(quantity)
  }

  render() {

    return (
      <div className="cart__item">
        <div className="cart__item__elements">
          <span className="font-weight-bold">
            { truncateText(this.props.name) }
          </span>
          { this.renderAdjustments() }
        </div>
        <div className="mt-2 d-flex align-items-center justify-content-between">
          <div className="cart__item__quantity">
            <DecrementQuantityButton
              disabled={ this.props.loading }
              onClick={ this.decrement.bind(this) } />
            <span className="px-2 font-weight-semi-bold">
              { this.props.quantity }
            </span>
            <IncrementQuantityButton
              disabled={ this.props.loading }
              onClick={ this.increment.bind(this) } />
          </div>
          <div>
            { this.props.showPricesTaxExcluded ? (
              <span className="font-weight-semi-bold">
                { (totalTaxExcluded(this.props) / 100).formatMoney() }
              </span>) : (
              <span className="font-weight-semi-bold">
                { (this.props.total / 100).formatMoney() }
              </span>)
            }
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    loading: state.isFetching,
    showPricesTaxExcluded: selectShowPricesTaxExcluded(state),
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeItem: itemID => dispatch(removeItem(itemID)),
    updateItemQuantity: (itemID, quantity) => dispatch(updateItemQuantity(itemID, quantity)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(CartItem)
