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
