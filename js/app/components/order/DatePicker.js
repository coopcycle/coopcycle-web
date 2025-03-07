import React, { useState } from 'react'
import _ from 'lodash'
import moment from 'moment'
import Select from 'react-select'
import { withTranslation } from 'react-i18next'

moment.locale($('html').attr('lang'))

const rangeAsTimeLabel = r => `${moment(r[0]).format('LT')} - ${moment(r[1]).format('LT')}`

const rangeAsDateValue = r => moment(r[0]).format('YYYY-MM-DD')

const dateAsOption = (d, t) => ({
  label: moment(d).calendar(null, {
    sameDay: t('DATEPICKER_TODAY'),
    nextDay: t('DATEPICKER_TOMORROW'),
    nextWeek: 'dddd D MMM' // TODO localized format
  }),
  value: d
})

const DatePicker = ({ choices, onChange, t, value }) => {

  const [ date, setDate ]   = useState(rangeAsDateValue(value))
  const [ range, setRange ] = useState(value)

  const choicesByDate =
    _.groupBy(choices, range => moment(range[0]).format('YYYY-MM-DD'))

  const dateOptions = _.keys(choicesByDate).map(d => dateAsOption(d, t))

  const choicesOnDate = choicesByDate[date] ?? []
  const timeOptions = choicesOnDate.map(r => ({
    label: rangeAsTimeLabel(r),
    value: r
  }))

  const timeValue =
    _.find(timeOptions, o => o.label === rangeAsTimeLabel(range)) || _.first(timeOptions)

  return (
    <div className="cart__date-picker">
      <div>
        <Select
          defaultValue={ _.find(dateOptions, o => o.value === date) }
          options={ dateOptions }
          onChange={ ({ value }) => {
            const ranges = choicesByDate[value]
            const r = _.find(ranges, r => rangeAsTimeLabel(r) === timeValue.label) || _.first(ranges)
            setDate(value)
            onChange(r)
          }} />
      </div>
      <div>
        <Select
          defaultValue={ _.find(timeOptions, o => o.value === range) }
          value={ timeValue }
          options={ timeOptions }
          onChange={ ({ value }) => {
            setRange(value)
            onChange(value)
          }} />
      </div>
    </div>
  )
}

export default withTranslation()(DatePicker)
