import { createSelector } from 'reselect'
import { apiSlice } from '../../api/slice'
import { selectOrderNodeId, selectShippingTimeRange } from './reduxSlice'

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

