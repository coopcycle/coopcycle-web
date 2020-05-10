import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import { selectIsSameRestaurant } from '../redux/selectors'

const Adjustment = ({ adjustment, tooltip }) => (
  <div>
    <span>{ adjustment.label }</span>
    { tooltip && (
      <span className="ml-1" data-toggle="tooltip" data-placement="right" title={ tooltip }>
        <i className="fa fa-info-circle"></i>
      </span>
    ) }
    <strong className="pull-right">{ (adjustment.amount / 100).formatMoney(2, window.AppData.currencySymbol) }</strong>
  </div>
)

class CartTotal extends React.Component {

  componentDidMount() {
    if (this.props.variableCustomerAmountEnabled) {
      $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
      })
    }
  }

  renderAdjustments() {

    const { variableCustomerAmountEnabled } = this.props

    let adjustments = []

    const deliveryAdjustments = this.props.adjustments.delivery || []

    if (Object.prototype.hasOwnProperty.call(this.props.adjustments, 'reusable_packaging')) {
      adjustments = adjustments.concat(this.props.adjustments.reusable_packaging)
    }

    if (Object.prototype.hasOwnProperty.call(this.props.adjustments, 'delivery_promotion')) {
      adjustments = adjustments.concat(this.props.adjustments.delivery_promotion)
    }

    let deliveryAdjustmentProps = {}
    if (variableCustomerAmountEnabled) {
      deliveryAdjustmentProps = {
        ...deliveryAdjustmentProps,
        tooltip: this.props.t('CART_DYNAMIC_DELIVERY_FEE'),
      }
    }

    if (adjustments.length > 0 || deliveryAdjustments.length > 0) {
      return (
        <div>
          { deliveryAdjustments.map(adjustment =>
            <Adjustment
              key={ adjustment.id }
              adjustment={ _.first(deliveryAdjustments) }
              { ...deliveryAdjustmentProps } />
          )}
          { adjustments.map(adjustment =>
            <Adjustment
              key={ adjustment.id }
              adjustment={ adjustment } />
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

  const { cart } = state
  const isSameRestaurant = selectIsSameRestaurant(state)

  let itemsTotal  = isSameRestaurant ? cart.itemsTotal : 0
  let total       = isSameRestaurant ? cart.total : 0
  let adjustments = isSameRestaurant ? cart.adjustments : {}

  return {
    itemsTotal,
    total,
    adjustments,
    variableCustomerAmountEnabled: cart.restaurant.variableCustomerAmountEnabled,
  }
}

export default connect(mapStateToProps)(withTranslation()(CartTotal))
