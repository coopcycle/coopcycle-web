import { apiSlice } from '../../redux/api/slice'
import { createSelector } from 'reselect'

export const selectOrderNodeId = state => state.order['@id']
export const selectShippingTimeRange = state => state.order.shippingTimeRange
export const selectPersistedTimeRange = state => state.order.persistedTimeRange

export const selectFirstAvailableTimeRange = createSelector(
  state => state,
  selectOrderNodeId,
  (state, orderNodeId) => {
    return apiSlice.endpoints.getOrderTiming.select(orderNodeId)(state)?.data?.range
  }
)

export const selectFulfilmentTimeRange = createSelector(
  selectShippingTimeRange,
  selectFirstAvailableTimeRange,
  (shippingTimeRange, firstAvailableTimeRange) => {
    return shippingTimeRange ?? firstAvailableTimeRange
  })


export const selectIsTimeRangeChangedModalOpen = state => state.ui.isTimeRangeChangedModalOpen
