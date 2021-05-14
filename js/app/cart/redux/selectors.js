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

export const selectItemsGroups = createSelector(
  selectItems,
  (items) =>  _.groupBy(items, 'vendor.name')
)

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

export const selectIsOrderingAvailable = createSelector(
  state => state.cart,
  state => state.times,
  (cart, { range, ranges }) => {

    const shippingTimeRange = cart.shippingTimeRange || range

    if (!shippingTimeRange && ranges.length === 0) {
      return false
    }

    return true
  }
)

const selectSortedErrors = createSelector(
  state => state.errors,
  (errors) => {

    // We don't display the error when restaurant has changed
    const filteredErrors = _.pickBy(errors, (value, key) => key !== 'restaurant')

    const errorsArray = _.reduce(filteredErrors, (acc, value, key) => {
      value.forEach(err => acc.push({ ...err, propertyPath: key }))
      return acc
    }, [])

    return errorsArray.sort((a, b) => {
      if (a.propertyPath === 'shippingTimeRange' && b.propertyPath !== 'shippingTimeRange') {
        return -1
      }

      return 0
    })
  }
)

export const selectErrorMessages = createSelector(
  selectSortedErrors,
  (errors) => {

    const messages = []
    _.forEach(errors, (error) => {
      if (error.propertyPath === 'shippingAddress') {
        messages.push(error.message)
      }
    })

    return messages
  }
)

export const selectWarningMessages = createSelector(
  selectSortedErrors,
  (errors) => {

    const messages = []
    _.forEach(errors, (error) => {
      if (error.propertyPath !== 'shippingAddress') {
        messages.push(error.message)
      }
    })

    return messages
  }
)
