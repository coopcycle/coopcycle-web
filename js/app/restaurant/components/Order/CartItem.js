import React from 'react'
import { connect } from 'react-redux'

import { totalTaxExcluded } from '../../../utils/tax'
import {
  DecrementQuantityButton,
  IncrementQuantityButton,
} from '../ChangeQuantityButton'

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
        <div className="cart__item__adjustments">
          { adjustments.menu_item_modifier.map((adjustment, index) =>
            <div key={ `cart-item-${this.props.id}-adjustment-${index}` }>
              <small>{ truncateText(adjustment.label) }</small>
              { adjustment.amount > 0 && (
                <small> (+{ (adjustment.amount / 100).formatMoney() })</small>
              )}
            </div>
          )}
        </div>
      )
    }
  }

  decrement() {
    const quantity = this.props.quantity - 1
    this.props.onChangeQuantity(quantity)
  }

  increment() {
    const quantity = this.props.quantity + 1
    this.props.onChangeQuantity(quantity)
  }

  render() {

    return (
      <div className="cart__item">
        <div className="cart__item__content">
          <div className="cart__item__content__body">
            <span className="cart__item__name">{ truncateText(this.props.name) }</span>
            { this.renderAdjustments() }
            <div className="cart__item__quantity">
              <DecrementQuantityButton
                disabled={ this.props.loading }
                onClick={ this.decrement.bind(this) }
              />
              <span className="cart__item__quantity__value">{ this.props.quantity }</span>
              <IncrementQuantityButton
                disabled={ this.props.loading }
                onClick={ this.increment.bind(this) } />
            </div>
          </div>
          <div className="cart__item__content__right">
            { this.props.showPricesTaxExcluded && (
              <span>
                { (totalTaxExcluded(this.props) / 100).formatMoney() }
              </span>) }
            { !this.props.showPricesTaxExcluded &&
              (<span>{ (this.props.total / 100).formatMoney() }</span>) }
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state) {

  return {
    loading: state.isFetching,
  }
}

export default connect(mapStateToProps)(CartItem)
