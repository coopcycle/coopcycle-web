import moment from 'moment'
import _ from 'lodash'
import TimeRange from '../utils/TimeRange'

/*
  Takes an opening interval as formatted in the DB and returns it as a human-readable string
 */
function openingHourIntervalToReadable(openingHourInterval, locale) {

  const { days, start, end } = TimeRange.parse(openingHourInterval)

  let startDay = _.first(days),
      endDay = _.last(days),
      formattedHours,
      formattedDays

  if (locale === 'fr') {
    formattedHours = ['de', start.replace(':', 'h'), 'à', end.replace(':', 'h')].join(' ')

    if (startDay === endDay) {
      formattedDays = 'Ouvert le ' + TimeRange.weekday(startDay, locale)
    } else {
      formattedDays = ['Ouvert du', TimeRange.weekday(startDay, locale), 'au', TimeRange.weekday(endDay, locale)].join(' ')
    }
  } else {
    formattedHours = [start.replace(':', 'h'), 'to', start.replace(':', 'h')].join(' ')

    if (startDay === endDay) {
      formattedDays = 'Open on ' + TimeRange.weekday(startDay, locale)
    } else {
      formattedDays = ['Open from', TimeRange.weekday(startDay, locale), 'to', TimeRange.weekday(endDay, locale)].join(' ')
    }
  }

  return [formattedDays, formattedHours].join(' ')
}

export default openingHourIntervalToReadable
