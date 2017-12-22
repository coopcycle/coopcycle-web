import moment from 'moment'
import _ from 'lodash'

const locale = $('html').attr('lang')

moment.locale('en')

let openingsWeekDays = moment.weekdaysMin()

moment.locale(locale)

let localeWeekDays = moment.weekdays()

let minDaysToLocaleDays =  {}


_.each(openingsWeekDays, function (item, index) {
  minDaysToLocaleDays[item] = localeWeekDays[index]
})

/*
  Takes an opening interval as formatted in the DB and returns it as a human-readable string
 */
function openingHourIntervalToReadable(openingHourInterval) {

  let splitted = openingHourInterval.split(/[ |-]/),
      startDay = splitted[0],
      endDay = splitted[1],
      startHour = splitted[2],
      endHour = splitted[3],
      formattedHours,
      formattedDays

  if (locale === 'fr') {
    formattedHours = ['de', startHour.replace(':', 'h'), 'à', endHour.replace(':', 'h')].join(' ')

    if (startDay === endDay) {
      formattedDays = 'Ouvert le ' + minDaysToLocaleDays[startDay]
    } else {
      formattedDays = ['Ouvert du', minDaysToLocaleDays[startDay], 'au', minDaysToLocaleDays[endDay]].join(' ')
    }
  } else {
    formattedHours = [startHour.replace(':', 'h'), 'to', endHour.replace(':', 'h')].join(' ')

    if (startDay === endDay) {
      formattedDays = 'Open on ' + minDaysToLocaleDays[startDay]
    } else {
      formattedDays = ['Open from', minDaysToLocaleDays[startDay], 'to', minDaysToLocaleDays[endDay]].join(' ')
    }
  }

  return [formattedDays, formattedHours].join(' ')
}

export default openingHourIntervalToReadable
