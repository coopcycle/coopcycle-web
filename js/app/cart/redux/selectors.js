import { createSelector } from 'reselect'
import _ from 'lodash'

export const selectIsDeliveryEnabled = createSelector(
  state => state.cart.restaurant.fulfillmentMethods,
  (fulfillmentMethods) => _.includes(fulfillmentMethods, 'delivery')
)

export const selectIsCollectionEnabled = createSelector(
  state => state.cart.restaurant.fulfillmentMethods,
  (fulfillmentMethods) => _.includes(fulfillmentMethods, 'collection')
)

export const selectIsSameRestaurant = createSelector(
  state => state.cart,
  state => state.restaurant,
  (cart, restaurant) => cart.restaurant.id === restaurant.id
)

export const selectItems = createSelector(
  state => state.cart.items,
  selectIsSameRestaurant,
  (items, isSameRestaurant) => isSameRestaurant ? items : []
)


