import { createSelector } from 'reselect'
import Moment from 'moment'
import { extendMoment } from 'moment-range'
import _ from 'lodash'

const moment = extendMoment(Moment)

const orderComparator = (a, b) => a['@id'] === b['@id']

const orderSort = (a, b) => {

  const rangeA = moment.range(a.shippingTimeRange)
  const rangeB = moment.range(b.shippingTimeRange)

  if (rangeA.start.isSame(rangeB.start)) {
    return 0
  }

  return rangeA.start.isBefore(rangeB.start) ? -1 : 1
}

export const selectOrders = createSelector(
  state => state.orders,
  state => state.searchQuery,
  state => state.searchResults,
  (orders, searchQuery, searchResults) => searchQuery.length > 0 ?
    _.intersectionWith(orders, searchResults, orderComparator) : orders
)

export const selectNewOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'new').sort(orderSort)
)

export const selectAcceptedOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'accepted').sort(orderSort)
)

export const selectFulfilledOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'fulfilled').sort(orderSort)
)

export const selectCancelledOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'refused' || o.state === 'cancelled').sort(orderSort)
)
