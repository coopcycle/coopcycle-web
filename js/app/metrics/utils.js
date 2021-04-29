export const getCubeDateRange = (dateRange) => {
  switch (dateRange) {
  case '30d':
    return 'Last 30 days'
  case '3mo':
    return 'Last 3 months'
  }

  return 'Last 30 days'
}
