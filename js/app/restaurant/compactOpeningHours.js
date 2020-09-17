import _ from 'lodash'
import moment from 'moment'
import TimeRange from '../utils/TimeRange'

export default (openingHours) => {

  const groupByDayPart = _.groupBy(openingHours, (oh) => TimeRange.getDayPart(oh))

  let result = []

  _.forEach(groupByDayPart, (ohs, dayPart) => {

    const ranges = _.map(ohs, oh => TimeRange.parse(oh))

    ranges.sort((a, b) => moment(a.start, 'hh:mm') < moment(b.start, 'hh:mm') ? -1 : 1)

    let arr = []
    let currentIndex = 0
    while (ranges.length > 0) {

      const range = ranges.shift()

      if (!arr[currentIndex]) {
        arr[currentIndex] = []
      }

      const linked = _.find(ranges, r => r.start === range.end)

      if (linked) {
        arr[currentIndex].push(range)
      } else {
        arr[currentIndex].push(range)
        ++currentIndex
      }
    }

    const compacted = _.map(arr, (ranges) => {
      const first = _.first(ranges)
      const last  = _.last(ranges)

      return `${dayPart} ${first.start}-${last.end}`
    })

    result = result.concat(compacted)
  })

  return result
}
