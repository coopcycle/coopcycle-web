import React, { useState, useEffect, useRef } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const { Option } = Select

function generateTimeSlots(disabled = false) {
  const items = []
  const minutes = [0, 15, 30, 45]
  new Array(24).fill().forEach((_, index) => {
    minutes.forEach(minute => {
      items.push({
        time: moment({ hour: index, minute: minute }),
        disabled,
      })
    })
  })
  return items
}

const DateTimeRangePicker = ({ defaultValue, onChange, format }) => {
  const [values, setValues] = useState(() =>
    defaultValue
      ? [moment(defaultValue.after), moment(defaultValue.before)]
      : [],
  )

  const firstSelectOptions = generateTimeSlots()
  const [secondSelectOptions, setSecondSelectOptions] =
    useState(generateTimeSlots())

  const [timeValues, setTimeValues] = useState(
    defaultValue
      ? {
          after: moment(defaultValue.after).format('HH:mm'),
          before: moment(defaultValue.before).format('HH:mm'),
        }
      : {},
  )

  const handleDateChange = newValue => {
    if (!newValue) return

    const afterHour = values[0].format('HH:mm:ss')
    const beforeHour = values[1].format('HH:mm:ss')

    const newDate = newValue.format('YYYY-MM-DD')

    setValues([
      moment(`${newDate} ${afterHour}`),
      moment(`${newDate} ${beforeHour}`),
    ])
  }

  const handleAfterHourChange = newValue => {
    setTimeValues(prevState => ({
      ...prevState,
      after: newValue,
    }))

    const after = moment(timeValues.after, 'HH:mm')

    const before = after.clone().add(15, 'minutes')
    setTimeValues(prevState => ({
      ...prevState,
      before: before.format('HH:mm'),
    }))

    const updatedSecondOptions = secondSelectOptions.map(option => {
      const isBefore = after.isBefore(option.time)
      return {
        ...option,
        disabled: !isBefore,
      }
    })
    setSecondSelectOptions(updatedSecondOptions)

    const date = values[0].format('YYYY-MM-DD')
    const afterValue = moment(`${date} ${newValue}:00`)

    setValues(prevArray => {
      const newValues = [...prevArray]
      newValues[0] = afterValue
      return newValues
    })
  }

  const handleBeforeHourChange = newValue => {
    setTimeValues(prevState => ({
      ...prevState,
      before: newValue,
    }))
    const date = values[0].format('YYYY-MM-DD')
    const beforeValue = moment(`${date} ${newValue}:00`)
    setValues(prevArray => {
      const newValues = [...prevArray]
      newValues[1] = beforeValue
      return newValues
    })
  }

  const isFirstRender = useRef(true)

  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      return
    }

    onChange(values)
  }, [values, onChange])

  return (
    <>
      <DatePicker
        style={{ width: '50%' }}
        format="LL"
        defaultValue={values[0]}
        onChange={newDate => {
          handleDateChange(newDate)
        }}
      />

      <Select
        style={{ width: '25%' }}
        format={format}
        value={timeValues.after}
        onChange={newAfterHour => {
          handleAfterHourChange(newAfterHour)
        }}>
        {firstSelectOptions.map(option => (
          <Option
            key={option.time.format('HH:mm')}
            value={option.time.format('HH:mm')}
            disabled={option.disabled}>
            {option.time.format('HH:mm')}
          </Option>
        ))}
      </Select>

      <Select
        style={{ width: '25%' }}
        format={format}
        value={timeValues.before}
        onChange={newBeforeHour => {
          handleBeforeHourChange(newBeforeHour)
        }}>
        {secondSelectOptions.map(option => (
          <Option
            key={option.time.format('HH:mm')}
            value={option.time.format('HH:mm')}
            disabled={option.disabled}>
            {option.time.format('HH:mm')}
          </Option>
        ))}
      </Select>
    </>
  )
}

export default function (el, options) {
  const defaultProps = {
    getDatePickerContainer: null,
    getTimePickerContainer: null,
    onChange: () => {},
    format: 'LLL', // verifier ce qu'on met là
  }

  const props = { ...defaultProps, ...options }

  render(
    <ConfigProvider locale={antdLocale}>
      <DateTimeRangePicker {...props} />
    </ConfigProvider>,
    el,
  )
}
