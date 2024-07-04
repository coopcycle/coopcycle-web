import moment from 'moment/moment'
import { apiSlice } from '../../api/slice'
import { openTimeRangeChangedModal } from '../../components/order/timeRange/reduxSlice'
import { selectOrderNodeId } from '../../entities/order/reduxSlice'
import { updateCartTiming } from '../../restaurant/redux/actions'

export function isTimeRangeSignificantlyDifferent(origRange, latestRange) {
  const displayedUpperBound = moment(origRange[1])
  const latestLowerBound = moment(latestRange[0])

  return latestLowerBound.diff(displayedUpperBound, 'hours') > 2
}

export const getTimingPathForStorage = orderNodeId =>
  `cpccl__chckt__order__${orderNodeId}__tmng`

export async function checkTimeRange(lastTimeRange, getState, dispatch) {
  if (!lastTimeRange) {
    // continue without the timing check
    return
  }

  const orderNodeId = selectOrderNodeId(getState())

  let latestTiming = null

  try {
    const result = await dispatch(
      apiSlice.endpoints.getOrderTiming.initiate(orderNodeId, {
        forceRefetch: true,
      }),
    )
    latestTiming = result.data
  } catch (error) {
    // ignore the request error and continue without the timing check
    return
  }

  if (!latestTiming) {
    // continue without the timing check
    return
  }

  // only used on the js/app/restaurant/item.js page
  dispatch(updateCartTiming(latestTiming))

  if (!latestTiming.range) {
    // no time ranges available; restaurant is closed for the coming days
    dispatch(openTimeRangeChangedModal())
    throw new Error('Time range is not available')
  }

  if (isTimeRangeSignificantlyDifferent(lastTimeRange, latestTiming.range)) {
    dispatch(openTimeRangeChangedModal())
    throw new Error('Time range is not available')
  }

  window.sessionStorage.setItem(
    getTimingPathForStorage(orderNodeId),
    JSON.stringify(latestTiming.range),
  )
}
