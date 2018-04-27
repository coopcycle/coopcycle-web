import moment from 'moment'
import _ from 'lodash'
import TimeRange from '../utils/TimeRange'

const templates = {
  en: {
    single: (day, open, close) => `Open on ${day} ${open} to ${close}`,
    range: (fromDay, toDay, open, close) => `Deliveries from ${fromDay} to ${toDay} ${open} to ${close}`
  },
  fr: {
    single: (day, open, close) => `Ouvert le ${day} de ${open} à ${close}`,
    range: (fromDay, toDay, open, close) => `Livraisons possibles du ${fromDay} au ${toDay} de ${open} à ${close}`
  }
}

/*
  Takes an opening interval as formatted in the DB and returns it as a human-readable string
 */
function openingHourIntervalToReadable(openingHourInterval, locale) {

  const { days, start, end } = TimeRange.parse(openingHourInterval)

  let startDay = _.first(days),
      endDay = _.last(days)

  const template = templates.hasOwnProperty(locale) ? templates[locale] : templates['en']
  const { single, range } = template

  // Format time according to locale

  const localeMoment = moment()
  localeMoment.locale(locale)

  const [ startHour, startMinute ] = start.split(':')
  const [ endHour, endMinute ] = end.split(':')

  const startFormatted = localeMoment.hour(startHour).minute(startMinute).format('LT')
  const endFormatted = localeMoment.hour(endHour).minute(endMinute).format('LT')

  if (startDay === endDay) {
    return single(TimeRange.weekday(startDay, locale), startFormatted, endFormatted)
  } else {
    return range(TimeRange.weekday(startDay, locale), TimeRange.weekday(endDay, locale), startFormatted, endFormatted)
  }
}

export default openingHourIntervalToReadable
