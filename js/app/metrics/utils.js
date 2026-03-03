import _ from 'lodash'

export const getCubeDateRange = (dateRange) => dateRange

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
