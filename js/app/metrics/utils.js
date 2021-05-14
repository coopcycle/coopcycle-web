export const dateRanges = {
  '30d': 'Last 30 days',
  'mo': 'This month',
  'lastmo': 'Last month',
  '3mo': 'Last 3 months'
}

export const getCubeDateRange = (key) => {

  if (Object.prototype.hasOwnProperty.call(dateRanges, key)) {

    return dateRanges[key]
  }

  return 'Last 30 days'
}
