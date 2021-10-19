export const dateRanges = {
  '30d': 'Last 30 days',
  'mo': 'This month',
  'lastmo': 'Last month',
  '3mo': 'Last 3 months'
}

const customRangeRegex = /([0-9-]+)\/([0-9-]+)/

export const getCubeDateRange = (key) => {

  const matches = key.match(customRangeRegex)
  if (matches) {

    return [ matches[1], matches[2] ]
  }

  if (Object.prototype.hasOwnProperty.call(dateRanges, key)) {

    return dateRanges[key]
  }

  return 'Last 30 days'
}
