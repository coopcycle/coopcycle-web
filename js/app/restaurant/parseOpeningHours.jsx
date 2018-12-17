import moment from 'moment'
import _ from 'lodash'
import TimeRange from '../utils/TimeRange'
import i18n from '../i18n'

/*
  Takes an opening interval as formatted in the DB and returns it as a human-readable string
 */
function openingHourIntervalToReadable(openingHourInterval, locale) {

  const { days, start, end } = TimeRange.parse(openingHourInterval)

  let startDay = _.first(days),
    endDay = _.last(days)

  // Format time according to locale

  const localeMoment = moment()
  localeMoment.locale(locale)

  const [ startHour, startMinute ] = start.split(':')
  const [ endHour, endMinute ] = end.split(':')

  const startFormatted = localeMoment.hour(startHour).minute(startMinute).format('LT')
  const endFormatted = localeMoment.hour(endHour).minute(endMinute).format('LT')

  if (startDay === endDay) {
    return i18n.t('OPENING_HOURS_SINGLE', {day: TimeRange.weekday(startDay, locale), open: startFormatted, close: endFormatted})
  } else {
    return i18n.t('OPENING_HOURS_RANGE', {fromDay: TimeRange.weekday(startDay, locale), toDay: TimeRange.weekday(endDay, locale), open: startFormatted, close: endFormatted})
  }
}

export default openingHourIntervalToReadable
