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
        <div className="cart__item__elements">
          <span className="font-weight-bold">
            { truncateText(this.props.name) }
          </span>
          { this.renderAdjustments() }
        </div>
        <div className="mt-3 d-flex align-items-center justify-content-between">
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
  }
}

export default connect(mapStateToProps)(CartItem)
