import moment from 'moment'
import _ from 'lodash'

export default class TimeRange {

  /**
   * Like moment.weekdaysMin, but with weeks always starting on Monday
   * @see https://momentjs.com/docs/
   * As of 2.13.0 you can pass a bool as the first parameter of the weekday functions.
   * If true, the weekdays will be returned in locale specific order.
   * Absent the locale specific parameter, weekdays always have Sunday as index 0, regardless of the local first day of the week.
   */
  static weekdaysMinNormalized() {
    // Copy the array to avoid modifying localeData
    let weekdaysMin = moment.localeData('en').weekdaysMin().slice()
    // Put Sunday at the end of the array
    const sunday = weekdaysMin.shift()
    weekdaysMin.push(sunday)

    return weekdaysMin
  }

  static weekdays(locale) {
    const map = _.zipObject(
      moment.localeData('en').weekdaysMin(),
      moment.localeData(locale).weekdays()
    )
    return _.map(this.weekdaysMinNormalized(), key => {
      return {
        key,
        name: map[key]
      }
    })
  }

  static weekday(weekdayMin, locale) {
    const weekday = _.find(this.weekdays(locale), weekday => weekday.key === weekdayMin)
    if (weekday) {
      return weekday.name
    }
  }

  static weekdaysShort(locale) {
    const map =  _.zipObject(
      moment.localeData('en').weekdaysMin(),
      moment.localeData(locale).weekdaysShort()
    )
    return _.map(this.weekdaysMinNormalized(), key => {
      return {
        key,
        name: map[key]
      }
    })
  }

  static getDayPart(text) {
    const matches = text.match(/(Mo|Tu|We|Th|Fr|Sa|Su)+-?(Mo|Tu|We|Th|Fr|Sa|Su)?/gi)

    return matches.join(',')
  }

  static parse(text) {

    const matches = text.match(/(Mo|Tu|We|Th|Fr|Sa|Su)+-?(Mo|Tu|We|Th|Fr|Sa|Su)?/gi)

    let days = []
    _.each(matches, (match) => {
      const isRange = _.includes(match, '-')
      if (isRange) {
        const [ start, end ] = match.split('-')
        let append = false
        _.each(this.weekdaysMinNormalized(), (weekday) => {
          if (weekday === start) {
            append = true
          }
          if (append) {
            days.push(weekday)
          }
          if (weekday === end) {
            append = false
          }
        })
      } else {
        days.push(match)
      }
    })

    let start = ''
    let end = ''

    const hours = text.match(/([0-9]{2}:[0-9]{2})-([0-9]{2}:[0-9]{2})/gi)
    if (hours.length === 1) {
      [ start, end ] = hours[0].split('-')
    }

    return { days, start, end }
  }
}
