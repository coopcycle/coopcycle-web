import React, { useState } from 'react'
import { ConfigProvider, DatePicker, Select } from 'antd'
import { antdLocale } from '../i18n'
import moment from 'moment'

import 'antd/es/input/style/index.css'

export default ({ initialChoices, onChange }) => {
  const datesWithTimeslots = {}

  initialChoices.forEach(choice => {
    const [date, hour] = choice.value.split(' ')
    if (Object.prototype.hasOwnProperty.call(datesWithTimeslots, date)) {
      datesWithTimeslots[date].push(hour)
    } else {
      datesWithTimeslots[date] = [hour]
    }
  })

  const dates = []

  for (const date in datesWithTimeslots) {
    dates.push(moment(date))
  }

  const [values, setValues] = useState({
    date: dates[0],
    option: datesWithTimeslots[dates[0].format('YYYY-MM-DD')][0],
  })

  const [options, setOptions] = useState(
    datesWithTimeslots[dates[0].format('YYYY-MM-DD')],
  )

  function disabledDate(current) {
    return !dates.some(date => date.isSame(current, 'day'))
  }

  const handleDateChange = newDate => {
    setValues({
      date: newDate,
      option: datesWithTimeslots[newDate.format('YYYY-MM-DD')][0],
    })
    setOptions(datesWithTimeslots[newDate.format('YYYY-MM-DD')])
    const formatedDate = newDate.format('YYYY-MM-DD')
    onChange(`${formatedDate} ${values.option}`)
  }

  const handleTimeSlotChange = newTimeslot => {
    setValues(prevState => ({ ...prevState, option: newTimeslot }))
    const formatedDate = values.date.format('YYYY-MM-DD')
    onChange(`${formatedDate} ${newTimeslot}`)
  }

  return (
    <ConfigProvider locale={antdLocale}>
      <div style={{ display: 'flex', marginTop: '0.5em' }}>
        <DatePicker
          style={{ width: '50%' }}
          className="mr-2"
          disabledDate={disabledDate}
          disabled={dates.length > 1 ? false : true}
          value={values.date}
          onChange={date => {
            handleDateChange(date)
          }}
        />

        <Select
          style={{ width: '35%' }}
          onChange={option => {
            handleTimeSlotChange(option)
          }}
          value={values.option}>
          {options.length >= 1 &&
            options.map(option => (
              <Select.Option key={option} value={option}>
                {option}
              </Select.Option>
            ))}
        </Select>
      </div>
    </ConfigProvider>
  )
}
