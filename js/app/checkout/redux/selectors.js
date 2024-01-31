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

export const selectItems = createSelector(
  state => state.cart.items,
  state => state.isPlayer,
  state => state.player.player,
  (items, isPlayer, player) => {
    if (!isPlayer) {
      return items;
    }
    return _.filter(items, (item) => {
      if (item.player === null) {
        return false
      }
      return item.player['@id'] === player
    })
  }
)

export const selectItemsGroups = createSelector(
  selectItems,
  (items) =>  _.groupBy(items, 'vendor.name')
)

export const selectPlayersGroups = createSelector(
  selectItems,
  (items) => _.groupBy(items, (item) => {
    if (item.player === null) {
      return 'Admin'
    }
    if (item.player.username !== undefined) {
      return item.player.username
    }
    return 'Unknown'
  })
)

export const selectShowPricesTaxExcluded = createSelector(
  state => state.country,
  (country) => country === 'ca'
)

export const selectItemsTotal = createSelector(
  selectItems,
  state => state.cart.itemsTotal,
  selectShowPricesTaxExcluded,
  state => state.isPlayer,
  (items, itemsTotal, showPricesTaxExcluded, isPlayer) => {

    if (showPricesTaxExcluded) {
      return _.reduce(items, (sum, item) => {
        return sum + totalTaxExcluded(item)
      }, 0)
    }

    if (isPlayer) {
      return _.reduce(items, (sum, item) => {
        if (showPricesTaxExcluded) {
          return sum + totalTaxExcluded(item)
        }
        return sum + item.total
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

export const selectReusablePackagingFeatureEnabled = createSelector(
  state => state.cart,
  (cart) => {
    if (cart.restaurant) {
      return cart.restaurant.loopeatEnabled
    }

    return true
  }
)

export const selectReusablePackagingEnabled = createSelector(
  state => state.cart,
  (cart) => {
    if (cart) {
      return cart.reusablePackagingEnabled
    }

    return false
  }
)
