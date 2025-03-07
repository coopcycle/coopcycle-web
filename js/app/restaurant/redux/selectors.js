import { createSelector } from 'reselect'
import _ from 'lodash'

import { totalTaxExcluded } from '../../utils/tax'

export const GROUP_ORDER_ADMIN = 'Admin'

export const selectIsFetching = state => state.isFetching

export const selectIsMobileCartVisible = state => state.isMobileCartVisible

export const selectRestaurant = state => state.restaurant

export const selectCart = state => state.cart

export const selectCartTotal = state => state.cart.total

/**
 * time range explicitly set by the customer
 * can be null
 */
export const selectCartShippingTimeRange = state => state.cart.shippingTimeRange

/**
 * Timing options for the current cart
 * if the value is present it means that the customer can order in this restaurant using the existing cart
 */
export const selectCartTiming = state => state.cartTiming

/**
 * Timing options for the shown restaurant
 * might be different from the cartTiming if the customer is browsing different restaurants
 */
const selectRestaurantTiming = state => state.restaurantTiming

export const selectCanAddToExistingCart = createSelector(
  selectCartTiming,
  (cartTiming) => Boolean(cartTiming))

export const selectFirstChoiceFulfilmentMethodForRestaurant = createSelector(
  selectRestaurantTiming,
  (restaurantTiming) => restaurantTiming.firstChoiceKey
)

export const selectAllFulfilmentMethodsForRestaurant = selectRestaurantTiming

export const selectIsCollectionEnabled = createSelector(
  selectAllFulfilmentMethodsForRestaurant,
  (allFulfilmentMethods) => allFulfilmentMethods['collection']?.range !== undefined
)

export const selectFulfilmentMethod = createSelector(
  selectCanAddToExistingCart,
  selectCart,
  selectFirstChoiceFulfilmentMethodForRestaurant,
  (canAddToExistingCart, cart, firstChoiceFulfilmentMethod) => {
    if (canAddToExistingCart) {
      return cart.takeaway ? 'collection' : 'delivery'
    } else {
      return firstChoiceFulfilmentMethod
    }
  })

export const selectFulfilmentTimeRange = createSelector(
  selectCanAddToExistingCart,
  selectCartShippingTimeRange,
  selectCartTiming,
  selectFulfilmentMethod,
  selectAllFulfilmentMethodsForRestaurant,
  (canAddToExistingCart, cartShippingTimeRange, cartTiming, fulfilmentMethod, allFulfilmentMethods) => {
    if (canAddToExistingCart) {
      return cartShippingTimeRange || cartTiming.range
    } else if (fulfilmentMethod) {
      return allFulfilmentMethods[fulfilmentMethod]?.range
    } else {
      return null
    }
  })

export const selectIsFulfilmentTimeSlotsAvailable = createSelector(
  selectFulfilmentTimeRange,
  (fulfilmentTimeRange) => Boolean(fulfilmentTimeRange))


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

export const selectHasItems = createSelector(
  selectItems,
  (items) => items.length > 0
)

export const selectItemsGroups = createSelector(
  selectItems,
  (items) =>  _.groupBy(items, 'vendor.name')
)

export const selectPlayersGroups = createSelector(
  selectItems,
  (items) => _.groupBy(items, (item) => {
    if (item.player === null) {
      return GROUP_ORDER_ADMIN
    }
    if (item.player.username !== undefined) {
      return item.player.username
    }
    return 'Unknown'
  })
)

export const selectIsPlayer = state => state.isPlayer
export const selectPlayer = state => state.player

export const selectIsGroupOrdersEnabled = state => state.isGroupOrdersEnabled

export const selectIsGroupOrderAdmin = createSelector(
  selectIsPlayer,
  selectPlayersGroups,
  (isPlayer, playersGroups) => {
    return !isPlayer && Object.keys(playersGroups).length > 1
  }
)

export const selectIsOrderAdmin = createSelector(
  selectIsPlayer,
  (isPlayer) => {
    // individual order: isPlayer == false
    // group order: only admin can order in a group order
    return !isPlayer
  })


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

export const selectSortedErrorMessages = createSelector(
  selectSortedErrors,
  (errors) => errors.map(error => error.message)
)

export const selectFulfillmentRelatedErrorMessages = createSelector(
  selectSortedErrors,
  (errors) => errors.filter(error =>
    error.propertyPath === 'shippingAddress'
    || error.propertyPath === 'shippingTimeRange').map(error => error.message),
)

export const selectCartItemsRelatedErrorMessages = createSelector(
  selectSortedErrors,
  (errors) => errors.filter(error =>
    error.propertyPath === 'items'
    || error.propertyPath === 'total').map(error => error.message),
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

export const selectIsTimeRangeChangedModalOpen = state => state.isTimeRangeChangedModalOpen
