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
  (orders, searchQuery, searchResults) => {
    if (searchQuery.length > 0) {
      // search results are ordered by relevance
      return searchResults.map(res => orders.find(o => orderComparator(o, res)))
    } else {
      return orders.sort(orderSort)
    }
  }
)

export const selectNewOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'new')
)

export const selectAcceptedOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'accepted')
)

export const selectStartedOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'started')
)

export const selectReadyOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'ready')
)

export const selectFulfilledOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'fulfilled')
)

export const selectCancelledOrders = createSelector(
  selectOrders,
  orders => _.filter(orders, o => o.state === 'refused' || o.state === 'cancelled')
)

export const selectHoursRanges = createSelector(
  state => state.date,
  date => {
    const dateAsRange = moment.range(
      moment(date).startOf('day'),
      moment(date).endOf('day')
    )

    const hoursRanges = []

    for (let start of dateAsRange.by('hour')) {
      const end = moment(start).add(1, 'hour')
      const hourRange = moment.range(start, end).toString()
      hoursRanges.push(hourRange)
    }

    return hoursRanges
  }
)

export const selectOrdersByHourRange = createSelector(
  selectHoursRanges,
  state => state.orders,
  (hoursRanges, orders) => {

    const groups = _.groupBy(orders, o => {
      return _.find(hoursRanges, hr => {
        const range = moment.rangeFromISOString(hr)
        const shippingTimeRange = moment.range(o.shippingTimeRange)

        return shippingTimeRange.overlaps(range)
      })
    })

    return _.map(groups, (value, key) => ({
      range: key,
      count: value.length,
      percentage: ((value.length * 100) / orders.length) / 100,
    }))
  }
)

export const selectItems = state => state.order ? state.order.items : []

export const selectItemsGroups = createSelector(
  selectItems,
  (items) =>  _.groupBy(items, 'vendor.name')
)

const selectColumnId = (state, id) => id;

const selectCollapsedColumns = state => state.preferences.collapsedColumns

export const selectIsCollapsedColumn = createSelector(
  selectColumnId,
  selectCollapsedColumns,
  (id, collapsedColumns) => collapsedColumns.includes(id)
)
