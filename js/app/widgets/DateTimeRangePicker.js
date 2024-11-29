import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import { ConfigProvider, DatePicker, Select } from 'antd'

import 'antd/es/input/style/index.css'

import { antdLocale } from '../i18n'

const { Option } = Select

function generateTimeSlots(disabled = false) {
  const items = []
  new Array(24).fill().forEach((_, index) => {
    items.push({
      time: moment({ hour: index }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 15 }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 30 }),
      disabled,
    })
    items.push({
      time: moment({ hour: index, minute: 45 }),
      disabled,
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

  useEffect(() => {
    console.log('timeValues.after dans useEffect :', timeValues.after)

    const after = moment(timeValues.after, 'HH:mm')

    const before = after.clone().add(15, 'minutes')
    setTimeValues(prevState => ({
      ...prevState,
      before: before.format('HH:mm'),
    }))
    console.log('before', before)

    const updatedSecondOptions = secondSelectOptions.map(option => {
      const isBefore = after.isBefore(option.time)
      return {
        ...option,
        disabled: !isBefore,
      }
    })

    setSecondSelectOptions(updatedSecondOptions)
  }, [timeValues.after])

  const handleChange = ({ type, newValue }) => {
    if (!newValue || !type) return // utile ?

    let afterValue = values[0]
    let beforeValue = values[1]

    switch (type) {
      case 'date':
        const afterHour = afterValue.format('HH:mm:ss')
        const beforeHour = beforeValue.format('HH:mm:ss')

        const newDate = newValue.format('YYYY-MM-DD')

        afterValue = moment(`${newDate} ${afterHour}`)
        beforeValue = moment(`${newDate} ${beforeHour}`)

        setValues([afterValue, beforeValue])

        break

      case 'afterHour':
        const date = afterValue.format('YYYY-MM-DD')
        afterValue = moment(`${date} ${newValue}:00`)
        setValues([afterValue, beforeValue])
        break

      case 'beforeHour': {
        const date = afterValue.format('YYYY-MM-DD')
        beforeValue = moment(`${date} ${newValue}:00`)
        setValues([afterValue, beforeValue])
        break
      }

      default:
        return
    }

    onChange({
      after: afterValue,
      before: beforeValue,
    })
  }

  return (
    <>
      <DatePicker
        style={{ width: '50%' }}
        format="LL"
        defaultValue={values[0]}
        onChange={newDate => {
          handleChange({ type: 'date', newValue: newDate })
        }}
      />
      <Select
        style={{ width: '25%' }}
        format={format}
        value={timeValues.after}
        onChange={newAfterHour => {
          handleChange({ type: 'afterHour', newValue: newAfterHour })
          setTimeValues(prevState => ({
            ...prevState,
            after: newAfterHour,
          }))
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
          handleChange({ type: 'beforeHour', newValue: newBeforeHour })
          setTimeValues(prevState => ({
            ...prevState,
            before: newBeforeHour,
          }))
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
