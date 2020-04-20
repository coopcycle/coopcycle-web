import React from 'react'
import Moment from 'moment'
import { extendMoment } from 'moment-range'

import i18n from '../i18n'

const moment = extendMoment(Moment)

moment.locale($('html').attr('lang'))

/**
 * @see https://stackoverflow.com/questions/4133859/round-up-to-nearest-multiple-of-five-in-php
 */
function roundUp(n, x = 5) {
    const value = (Math.round(n) % x === 0) ? Math.round(n) : Math.round((n + x / 2) / x) * x

    return Math.trunc(value)
}

export const asText = (value, short) => {

  // FIXME Make sure that value is not null / an array of 2 strings
  const range = moment.range(
    moment(value[0]),
    moment(value[1])
  )

  if (short === true) {

    return `${range.start.format('LT')} - ${range.end.format('LT')}`
  }

  const today = moment.range(
    moment().set({ hour: 0, minute: 0, second: 0 }),
    moment().set({ hour: 23, minute: 59, second: 59 })
  )
  const tomorrow = moment.range(
    moment().add(1, 'day').set({ hour: 0, minute: 0, second: 0 }),
    moment().add(1, 'day').set({ hour: 23, minute: 59, second: 59 })
  )

  const isToday = range.overlaps(today)
  const isTomorrow = range.overlaps(tomorrow)

  let text = ''
  if (isToday) {

    const startDiff = roundUp(range.start.diff(moment(), 'minutes'), 5)
    const endDiff   = roundUp(range.end.diff(moment(), 'minutes'), 5)

    // We see it as "fast" if it's less than max. 45 minutes
    if (endDiff <= 45) {
      text = i18n.t('CART_DELIVERY_TIME_DIFF', {
        diff: `${startDiff} - ${endDiff}`
      })
    } else {
      text = i18n.t('CART_DELIVERY_TIME_RANGE_TODAY', {
        start: range.start.format('LT'),
        end: range.end.format('LT'),
      })
    }

  } else if (isTomorrow) {
    text = i18n.t('CART_DELIVERY_TIME_RANGE_TOMORROW', {
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })
  } else {
    text = i18n.t('CART_DELIVERY_TIME_RANGE_LATER', {
      date: moment(range.start).calendar(null, {
        sameDay: i18n.t('DATEPICKER_TODAY'),
        nextDay: i18n.t('DATEPICKER_TOMORROW'),
        nextWeek: 'LL',
        sameElse: 'LL',
      }),
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })
  }

  return text
}

export default ({ value, short }) => (
  <span>{ asText(value, short) }</span>
)
