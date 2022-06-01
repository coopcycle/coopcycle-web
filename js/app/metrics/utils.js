import _ from 'lodash'

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

export const getTasksFilters = (tags) => {
  let filters = [
    {
      "member": "Task.status",
      "operator": "notEquals",
      "values": [
        "CANCELLED"
      ]
    }
  ]

  if (tags.length > 0) {
    filters.push({
      "member": "Tag.slug",
      "operator": "equals",
      "values": _.map(tags, tag => tag.slug)
    })
  }

  return filters
}
