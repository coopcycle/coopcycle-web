import React, { useState } from "react"
import { ConfigProvider, DatePicker, Select } from 'antd'
import { antdLocale } from '../i18n'
import moment from 'moment'

import 'antd/es/input/style/index.css'

export default ({ initialChoices, onChange }) => {
  const [date, setDate] = useState()
  const [options, setOptions] = useState([])

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

  function disabledDate(current) {
    return !dates.some(date => date.isSame(current, 'day'))
  }

  const handleSlotChange = newTimeSlot => {
    const formatedDate = date.format('YYYY-MM-DD')
    onChange(`${formatedDate} ${newTimeSlot}`)
  }

  return (
    <ConfigProvider locale={antdLocale}>
      <div style={{ marginTop: '0.5em' }}>
        <DatePicker
          style={{ width: '60%' }}
          disabledDate={disabledDate}
          onChange={date => {
            setDate(date)
            setOptions(datesWithTimeslots[date.format('YYYY-MM-DD')])
          }}
        />
        <Select
          style={{ width: '35%' }}
          onChange={timeslot => handleSlotChange(timeslot)}>
          {options.length > 1 &&
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
