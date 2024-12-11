import React, { useState, useEffect } from 'react'
import { ConfigProvider, DatePicker, Select, Radio } from 'antd'
import { antdLocale } from '../i18n'
import moment from 'moment'

import 'antd/es/input/style/index.css'

export default ({ choices, initialChoices, onChange }) => {
  const timeSlotsLabel = []
  for (const timeSlot in choices) {
    timeSlotsLabel.push(timeSlot)
  }

  const [timeSlotChoices, setTimeSlotChoices] = useState(initialChoices)

  const datesWithTimeslots = {}

  timeSlotChoices.forEach(choice => {
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

  const [values, setValues] = useState({})

  const [options, setOptions] = useState([])

  useEffect(() => {
    setOptions(datesWithTimeslots[dates[0].format('YYYY-MM-DD')])
    setValues({
      date: dates[0],
      option: datesWithTimeslots[dates[0].format('YYYY-MM-DD')][0],
    })
  }, [timeSlotChoices])

  const handleInitialChoicesChange = timeSlot => {
    setTimeSlotChoices(choices[timeSlot.target.value])
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
      {Object.keys(choices).length > 1 ? (
        <Radio.Group
          defaultValue={timeSlotsLabel[0]}
          buttonStyle="solid"
          style={{ display: 'flex' }}>
          {timeSlotsLabel.map(label => (
            <Radio.Button
              key={label}
              value={label}
              onChange={timeSlot => {
                handleInitialChoicesChange(timeSlot)
              }}>
              {label}
            </Radio.Button>
          ))}
        </Radio.Group>
      ) : null}

      <div style={{ display: 'flex', marginTop: '0.5em' }}>
        <DatePicker
          format="LL"
          style={{ width: '60%' }}
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
