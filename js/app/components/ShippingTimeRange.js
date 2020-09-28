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

  if (!value || !Array.isArray(value) || value.length !== 2) {
    return ''
  }

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

  const isToday = range.overlaps(today)

  const startDiff = roundUp(range.start.diff(moment(), 'minutes'), 5)
  const endDiff   = roundUp(range.end.diff(moment(), 'minutes'), 5)

  // We see it as "fast" if it's less than max. 45 minutes
  const isFast = endDiff <= 45

  let text = ''
  if (isToday && isFast) {
    text = i18n.t('CART_DELIVERY_TIME_DIFF', {
      diff: `${startDiff} - ${endDiff}`
    })
  } else {

    const rangeAsText = i18n.t('TIME_RANGE', {
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })

    const sameElse =
      i18n.t('CART_DELIVERY_TIME_RANGE_SAME_ELSE', { range: rangeAsText })

    text = moment(range.start).calendar(null, {
      sameDay:  i18n.t('CART_DELIVERY_TIME_RANGE_SAME_DAY', { range: rangeAsText }),
      nextDay:  i18n.t('CART_DELIVERY_TIME_RANGE_NEXT_DAY', { range: rangeAsText }),
      nextWeek: i18n.t('CART_DELIVERY_TIME_RANGE_NEXT_WEEK', { range: rangeAsText }),
      lastDay:  i18n.t('CART_DELIVERY_TIME_RANGE_LAST_DAY', { range: rangeAsText }),
      lastWeek: sameElse,
      sameElse: sameElse,
    })
  }

  return text
}

export default ({ value, short }) => (
  <span>{ asText(value, short) }</span>
)
