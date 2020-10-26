import { createSelector } from 'reselect'
import _ from 'lodash'

import { totalTaxExcluded } from '../../utils/tax'

export const selectIsDeliveryEnabled = createSelector(
  state => state.cart.vendor.fulfillmentMethods,
  (fulfillmentMethods) => _.includes(fulfillmentMethods, 'delivery')
)

export const selectIsCollectionEnabled = createSelector(
  state => state.cart.vendor.fulfillmentMethods,
  (fulfillmentMethods) => _.includes(fulfillmentMethods, 'collection')
)

export const selectItems = state => state.cart.items

export const selectShowPricesTaxExcluded = createSelector(
  state => state.country,
  (country) => country === 'ca'
)

export const selectItemsTotal = createSelector(
  selectItems,
  state => state.cart.itemsTotal,
  selectShowPricesTaxExcluded,
  (items, itemsTotal, showPricesTaxExcluded) => {

    if (showPricesTaxExcluded) {
      return _.reduce(items, (sum, item) => {
        return sum + totalTaxExcluded(item)
      }, 0)
    }

    return itemsTotal
  }
)

export const selectVariableCustomerAmountEnabled = createSelector(
  state => state.cart,
  (cart) => {
    if (cart.vendor) {
      return cart.vendor.variableCustomerAmountEnabled
    }

    return false
  }
)
