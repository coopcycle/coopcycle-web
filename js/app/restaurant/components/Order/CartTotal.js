import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import {
  selectShowPricesTaxExcluded,
  selectItems,
  selectItemsTotal,
  selectVariableCustomerAmountEnabled } from '../../redux/selectors'

const Adjustment = ({ adjustment, tooltip }) => (
  <div>
    <span>{ adjustment.label }</span>
    { tooltip && (
      <span className="ml-1" data-toggle="tooltip" data-placement="right" title={ tooltip }>
        <i className="fa fa-info-circle"></i>
      </span>
    ) }
    <span className="pull-right">{ (adjustment.amount / 100).formatMoney() }</span>
  </div>
)

const groupTaxAdjustments = (adjustments, items) => {

  const itemTaxAdjustments = _.reduce(items, (acc, item) => {
    if (Object.prototype.hasOwnProperty.call(item.adjustments, 'tax')) {
      return acc.concat(item.adjustments.tax)
    }

    return acc
  }, [])

  const merged  = adjustments.concat(itemTaxAdjustments)
  const grouped = _.groupBy(merged, 'label')

  return _.map(grouped, (items, key) => {
    return {
      label: key,
      amount: _.sumBy(items, 'amount')
    }
  })
}

class CartTotal extends React.Component {

  componentDidMount() {
    if (this.props.variableCustomerAmountEnabled) {
      $('body').tooltip({
        selector: '[data-toggle="tooltip"]'
      })
    }
  }

  renderAdjustments() {

    const { items, variableCustomerAmountEnabled, showPricesTaxExcluded } = this.props

    let adjustments = []
    let taxAdjustments = []

    if (Object.prototype.hasOwnProperty.call(this.props.adjustments, 'tax')) {
      taxAdjustments = this.props.adjustments.tax
    }

    const deliveryAdjustments = this.props.adjustments.delivery || []

    if (Object.prototype.hasOwnProperty.call(this.props.adjustments, 'reusable_packaging')) {
      adjustments = adjustments.concat(this.props.adjustments.reusable_packaging)
    }

    if (Object.prototype.hasOwnProperty.call(this.props.adjustments, 'order_promotion')) {
      adjustments = adjustments.concat(this.props.adjustments.order_promotion)
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

    return (
      <>
        { deliveryAdjustments.map(adjustment =>
          <Adjustment
            key={ adjustment.id }
            adjustment={ _.first(deliveryAdjustments) }
            { ...deliveryAdjustmentProps } />
        )}
        { showPricesTaxExcluded && groupTaxAdjustments(taxAdjustments, items).map(adjustment =>
          <Adjustment
            key={ adjustment.label }
            adjustment={ adjustment } />
        )}
        { adjustments.map(adjustment =>
          <Adjustment
            key={ adjustment.id }
            adjustment={ adjustment } />
        )}
      </>
    )
  }

  render() {

    const { itemsTotal } = this.props

    if (itemsTotal > 0) {
      return (
        <div className="cart__total">
          <div>
            <span>{ this.props.t('CART_TOTAL_PRODUCTS') }</span>
            <span className="pull-right">{ (itemsTotal / 100).formatMoney() }</span>
          </div>
          { this.renderAdjustments() }
        </div>
      )
    } else {
      return null
    }
  }
}

function mapStateToProps (state) {

  return {
    items: selectItems(state),
    itemsTotal: selectItemsTotal(state),
    adjustments: state.cart.adjustments,
    variableCustomerAmountEnabled: selectVariableCustomerAmountEnabled(state),
    showPricesTaxExcluded: selectShowPricesTaxExcluded(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(CartTotal))
