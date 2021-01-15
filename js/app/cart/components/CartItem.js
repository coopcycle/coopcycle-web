import React from 'react'
import { connect } from 'react-redux'

import { totalTaxExcluded } from '../../utils/tax'

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
          { adjustments.menu_item_modifier.map(adjustment =>
            <div key={ adjustment.id }>
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

    const btnProps = {
      disabled: this.props.loading
    }

    return (
      <div className="cart__item">
        <div className="cart__item__content">
          <div className="cart__item__content__remove">
            <a href="#" onClick={ this.props.onClickRemove }>
              <i className="fa fa-lg fa-times"></i>
            </a>
          </div>
          <div className="cart__item__content__left">
            <div className="cart__item__quantity">
              <button type="button" className="cart__item__quantity__decrement"
                onClick={ this.decrement.bind(this) } { ...btnProps }>
                <i className="fa fa-lg fa-minus-circle"></i>
              </button>
              <span>{ this.props.quantity }</span>
              <button type="button" className="cart__item__quantity__increment"
                onClick={ this.increment.bind(this) } { ...btnProps }>
                <i className="fa fa-lg fa-plus-circle"></i>
              </button>
            </div>
          </div>
          <div className="cart__item__content__body">
            <span>{ truncateText(this.props.name) }</span>
            { this.renderAdjustments() }
          </div>
          <div className="cart__item__content__right">
            { this.props.showPricesTaxExcluded && (<span>{ (totalTaxExcluded(this.props) / 100).formatMoney() }</span>) }
            { !this.props.showPricesTaxExcluded && (<span>{ (this.props.total / 100).formatMoney() }</span>) }
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    loading: state.isFetching,
  }
}

export default connect(mapStateToProps)(CartItem)
