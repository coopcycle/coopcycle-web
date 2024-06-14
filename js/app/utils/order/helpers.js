import moment from 'moment/moment'

export function isTimeRangeSignificantlyDifferent(origRange, latestRange) {
  const displayedUpperBound = moment(origRange[1])
  const latestLowerBound = moment(latestRange[0])

  return latestLowerBound.diff(displayedUpperBound, 'hours') > 2
}

export const getTimingPathForStorage = (orderNodeId) => `cpccl__chckt__order__${ orderNodeId }__tmng`
