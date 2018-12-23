import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'

import CartItem from './CartItem'

class CartTotal extends React.Component {

  renderAdjustments() {

    const { adjustments } = this.props

    if (adjustments.hasOwnProperty('delivery')) {
      return (
        <div>
          { adjustments.delivery.map(adjustment =>
            <div key={ adjustment.id }>
              <span>{ adjustment.label }</span>
              <strong className="pull-right">{ (adjustment.amount / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
            </div>
          )}
        </div>
      )
    }
  }

  render() {

    const { total, itemsTotal } = this.props

    if (itemsTotal > 0) {
      return (
        <div>
          <div>
            <span>{ this.props.t('CART_TOTAL_PRODUCTS') }</span>
            <strong className="pull-right">{ (itemsTotal / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
          </div>
          { this.renderAdjustments() }
          <div>
            <span>{ this.props.t('CART_TOTAL') }</span>
            <strong className="pull-right">{ (total / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
          </div>
          <hr />
        </div>
      )
    }

    return (
      <div></div>
    )
  }

}

function mapStateToProps (state) {
  return {
    itemsTotal: state.cart.itemsTotal,
    total: state.cart.total,
    adjustments: state.cart.adjustments,
  }
}

export default connect(mapStateToProps)(translate()(CartTotal))
