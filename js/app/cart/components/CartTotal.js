import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import { selectIsSameRestaurant, selectShowPricesTaxExcluded, selectItemsTotal } from '../redux/selectors'

const Adjustment = ({ adjustment, tooltip }) => (
  <div>
    <span>{ adjustment.label }</span>
    { tooltip && (
      <span className="ml-1" data-toggle="tooltip" data-placement="right" title={ tooltip }>
        <i className="fa fa-info-circle"></i>
      </span>
    ) }
    <strong className="pull-right">{ (adjustment.amount / 100).formatMoney() }</strong>
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
      <div>
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
      </div>
    )
  }

  render() {

    const { total, itemsTotal } = this.props

    if (itemsTotal > 0) {
      return (
        <div>
          <div>
            <span>{ this.props.t('CART_TOTAL_PRODUCTS') }</span>
            <strong className="pull-right">{ (itemsTotal / 100).formatMoney() }</strong>
          </div>
          { this.renderAdjustments() }
          <div>
            <span>{ this.props.t('CART_TOTAL') }</span>
            <strong className="pull-right">{ (total / 100).formatMoney() }</strong>
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

  let items       = isSameRestaurant ? cart.items : []
  let total       = isSameRestaurant ? cart.total : 0
  let adjustments = isSameRestaurant ? cart.adjustments : {}

  return {
    items,
    itemsTotal: selectItemsTotal(state),
    total,
    adjustments,
    variableCustomerAmountEnabled: cart.restaurant.variableCustomerAmountEnabled,
    showPricesTaxExcluded: selectShowPricesTaxExcluded(state),
  }
}

export default connect(mapStateToProps)(withTranslation()(CartTotal))
