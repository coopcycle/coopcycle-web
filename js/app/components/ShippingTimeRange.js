import React from 'react'
import Moment from 'moment'
import { extendMoment } from 'moment-range'

import i18n from '../i18n'

const moment = extendMoment(Moment)

moment.locale($('html').attr('lang'))

export default ({ value }) => {

  const today = moment.range(
    moment().set({ hour: 0, minute: 0, second: 0 }),
    moment().set({ hour: 23, minute: 59, second: 59 })
  )
  const tomorrow = moment.range(
    moment().add(1, 'day').set({ hour: 0, minute: 0, second: 0 }),
    moment().add(1, 'day').set({ hour: 23, minute: 59, second: 59 })
  )

  const range = moment.range(
    moment(value[0]),
    moment(value[1])
  )

  const isToday = range.overlaps(today)
  const isTomorrow = range.overlaps(tomorrow)

  let text = ''
  if (isToday) {
    text = i18n.t('CART_DELIVERY_TIME_RANGE_TODAY', {
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })
  } else if (isTomorrow) {
    text = i18n.t('CART_DELIVERY_TIME_RANGE_TOMORROW', {
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })
  } else {
    text = i18n.t('CART_DELIVERY_TIME_RANGE_LATER', {
      date: moment(range.start).calendar(),
      start: range.start.format('LT'),
      end: range.end.format('LT'),
    })
  }

  return (
    <span>{ text }</span>
  )
}
